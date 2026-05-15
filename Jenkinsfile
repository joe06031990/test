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

        stage('Backup Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e 

                # CHANGED: Folder renamed to your custom preference
                BACKUP_DIR="dashboard_not_viewed_in_30_days"
                mkdir -p "$BACKUP_DIR"

                echo "========================================================="
                echo "Fetching all dashboard metadata from Grafana..."
                echo "========================================================="
                
                SEARCH_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    "$GRAFANA_URL/api/search?type=dash-db")

                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | jq -r '.uid')
                    TITLE=$(echo "$dash" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER=$(echo "$dash" | jq -r '.folderTitle' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    
                    if [ "$FOLDER" == "null" ] || [ -z "$FOLDER" ]; then
                        FOLDER="General"
                    fi

                    # Fetch full dashboard data to analyze metadata dates
                    FULL_DASH=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                        "$GRAFANA_URL/api/dashboards/uid/$DASH_UID")

                    UPDATED_AT=$(echo "$FULL_DASH" | jq -r '.meta.updated // empty')
                    
                    if [ -n "$UPDATED_AT" ]; then
                        UPDATED_EPOCH=$(date -d "${UPDATED_AT}" +%s 2>/dev/null || date -f - "${UPDATED_AT}" +%s 2>/dev/null)
                        THIRTY_DAYS_AGO=$(date -d '30 days ago' +%s)

                        # Skip dashboards that have been actively changed within 30 days
                        if [ "$UPDATED_EPOCH" -gt "$THIRTY_DAYS_AGO" ]; then
                            echo "Skipping active dashboard (modified within 30 days): $FOLDER / $TITLE"
                            continue
                        fi
                    fi

                    # Save the stale dashboard model into the new folder structure
                    mkdir -p "$BACKUP_DIR/$FOLDER"
                    echo "--> BACKING UP STALE DASHBOARD: $FOLDER / $TITLE ($DASH_UID)"
                    
                    echo "$FULL_DASH" | jq '.dashboard | .id = null' > "$BACKUP_DIR/$FOLDER/${TITLE}_${DASH_UID}.json"
                        
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
                    
                    git config user.name "Jenkins Backup Bot"
                    git config user.email "jenkins@your-company.com"
                    
                    # Safely stage the new directory only if it exists and contains items
                    if [ -d "dashboard_not_viewed_in_30_days" ]; then
                        git add dashboard_not_viewed_in_30_days/
                    fi
                    
                    # Evaluate structural diff modifications
                    if git diff-index --quiet HEAD --; then
                        echo "No new stale dashboards detected or created. Skipping commit."
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
