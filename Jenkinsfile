pipeline {
    agent any
    
    environment {
        GRAFANA_TOKEN = credentials('GRAFANA_API_KEY')
        GRAFANA_URL   = 'https://jstest2025.grafana.net'
        GIT_REPO_URL  = 'https://github.com/joe06031990/test'
    }

    stages {
        stage('Clone Backup Repo') {
            steps {
                git branch: 'master', url: "${GIT_REPO_URL}"
            }
        }

        stage('Backup Stale Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e 

                BACKUP_DIR="dashboards"
                mkdir -p "$BACKUP_DIR"

                echo "========================================================="
                echo "1. Querying Grafana Cloud Usage Metrics (Last 30 Days)..."
                echo "========================================================="

                # We query the Prometheus API proxy using the enterprise view metric
                # This calculates any dashboard that has had an increase in views over 30 days
                PROM_QUERY='sum(increase(grafana_api_dashboard_view_total[30d])) by (uid) > 0'
                
                METRICS_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    --data-urlencode "query=${PROM_QUERY}" \
                    "$GRAFANA_URL/api/datasources/proxy/uid/grafanacloud-usage-insights/api/v1/query")

                # Safely parse the UIDs directly out of the metric labels
                ACTIVE_UIDS=$(echo "$METRICS_RESP" | jq -r '.data.result[].metric.uid // empty' | sort -u)

                echo "Found active dashboard UIDs in last 30 days:"
                if [ -z "$ACTIVE_UIDS" ]; then
                    echo "[NONE]"
                else
                    echo "$ACTIVE_UIDS"
                fi
                echo "========================================================="

                echo "2. Fetching all dashboard metadata..."
                SEARCH_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    "$GRAFANA_URL/api/search?type=dash-db")

                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | jq -r '.uid')
                    TITLE=$(echo "$dash" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER=$(echo "$dash" | jq -r '.folderTitle' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    
                    if [ "$FOLDER" == "null" ] || [ -z "$FOLDER" ]; then
                        FOLDER="General"
                    fi

                    # Check if our current DASH_UID exists in the ACTIVE_UIDS list
                    if echo "$ACTIVE_UIDS" | grep -qx "$DASH_UID"; then
                        echo "Skipping active dashboard (viewed within 30 days): $FOLDER / $TITLE"
                        continue
                    fi

                    # If it wasn't skipped, it's stale! Back it up.
                    mkdir -p "$BACKUP_DIR/$FOLDER"
                    echo "--> BACKING UP STALE DASHBOARD: $FOLDER / $TITLE ($DASH_UID)"
                    
                    curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                        "$GRAFANA_URL/api/dashboards/uid/$DASH_UID" | \
                        jq '.dashboard | .id = null' > "$BACKUP_DIR/$FOLDER/${TITLE}_${DASH_UID}.json"
                        
                done
                
                echo "Dashboard extraction complete."
                '''
            }
        }
        
        stage('Commit to Git') {
            steps {
                // IMPORTANT: Replace 'YOUR_GITHUB_CREDENTIAL_ID' with your Jenkins GitHub Credential ID
                withCredentials([usernamePassword(credentialsId: '370af9a5-4d10-4db5-8f4a-4ef5411d1d7e', passwordVariable: 'GIT_PASSWORD', usernameVariable: 'GIT_USERNAME')]) {
                    sh '''#!/bin/bash
                    set -e
                    
                    git config user.name "Jenkins Backup Bot"
                    git config user.email "jenkins@your-company.com"
                    
                    git add dashboards/
                    
                    if git diff-index --quiet HEAD --; then
                        echo "No new stale dashboards detected. Skipping commit."
                    else
                        git commit -m "Automated Grafana Stale Dashboard Backup: $(date +'%Y-%m-%d')"
                        git push https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/joe06031990/test.git master
                        echo "Successfully pushed updates to GitHub."
                    fi
                    '''
                }
            }
        }
    }
    
    post {
        always {
            cleanWs()
        }
    }
}
