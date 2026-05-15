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
                echo "1. Querying Grafana Cloud Usage Insights via LogQL..."
                echo "========================================================="

                # Use native LogQL JSON extraction to unpack the fields dynamically
                LOKI_QUERY='{namespace="usage-insights"} | json | type="dashboard_view"'
                
                # Fetching logs up to 5000 entries within the last 30 days
                START_TIME=$(date -d '30 days ago' +%s)

                METRICS_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    --data-urlencode "query=${LOKI_QUERY}" \
                    --data-urlencode "limit=5000" \
                    --data-urlencode "start=${START_TIME}" \
                    "$GRAFANA_URL/api/datasources/proxy/uid/grafanacloud-usage-insights/loki/api/v1/query_range")

                # Parse the extracted JSON fields directly using jq rather than text filters
                ACTIVE_UIDS=$(echo "$METRICS_RESP" | jq -r '.data.result[].values[][1] | fromjson | .dashboard_uid // empty' 2>/dev/null | sort -u || true)

                echo "Found active dashboard UIDs in last 30 days:"
                if [ -z "$ACTIVE_UIDS" ]; then
                    echo "[NONE - No dashboard views recorded in logs]"
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

                    # Match check: If found in active list, skip it!
                    if [ -n "$ACTIVE_UIDS" ] && echo "$ACTIVE_UIDS" | grep -qx "$DASH_UID"; then
                        echo "Skipping active dashboard (viewed within 30 days): $FOLDER / $TITLE"
                        continue
                    fi

                    # Otherwise, back it up
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
