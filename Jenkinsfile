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

                BACKUP_DIR="dashboards_not_used_in_30_days"
                mkdir -p "$BACKUP_DIR"

                echo "========================================================="
                echo "1. Querying Grafana Cloud Usage Insights (Last 30 Days)..."
                echo "========================================================="

                # Target the managed usage insights Loki data source directly via proxy API
                # LogQL query filters for dashboard views and extracts the dashboard UID
                LOKI_QUERY='{namespace="usage-insights"} |= "type=\\"dashboard_view\\""'
                
                ACTIVE_UIDS_JSON=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    --data-urlencode "query=${LOKI_QUERY}" \
                    --data-urlencode "direction=forward" \
                    --data-urlencode "limit=5000" \
                    --data-urlencode "start=$(date -d '30 days ago' +%s)" \
                    "$GRAFANA_URL/api/datasources/proxy/uid/grafanacloud-usage-insights/loki/api/v1/query_range")

                # Parse out unique dashboard UIDs using jq regex matching on the log line string
                ACTIVE_UIDS=$(echo "$ACTIVE_UIDS_JSON" | jq -r '.data.result[].values[][1]' | grep -o 'dashboard_uid=[^, ]*' | cut -d= -f2 | sort -u)

                echo "Found active dashboard UIDs in last 30 days:"
                echo "$ACTIVE_UIDS"
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

                    # ---------------------------------------------------------
                    # THE 30-DAY FILTER LOGIC
                    # Check if our current DASH_UID exists in the ACTIVE_UIDS list
                    # ---------------------------------------------------------
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
