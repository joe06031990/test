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

                BACKUP_DIR="dashboard_not_viewed_in_30_days"
                mkdir -p "$BACKUP_DIR"

                echo "========================================================="
                echo "Building Grafana nested folder hierarchy map..."
                echo "========================================================="
                
                # 1. Create associative arrays to store folder relationships
                declare -A FOLDER_TITLE_MAP
                declare -A FOLDER_PARENT_MAP

                FOLDERS_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/folders")
                
                # Parse all folders and map their UIDs to their titles and parent UIDs
                if [ -n "$FOLDERS_RESP" ] && echo "$FOLDERS_RESP" | jq -e '.[]' >/dev/null 2>&1; then
                    while read -r folder; do
                        f_uid=$(echo "$folder" | jq -r '.uid')
                        f_title=$(echo "$folder" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                        f_parent=$(echo "$folder" | jq -r '.parentUid // empty')
                        
                        FOLDER_TITLE_MAP["$f_uid"]="$f_title"
                        FOLDER_PARENT_MAP["$f_uid"]="$f_parent"
                    done < <(echo "$FOLDERS_RESP" | jq -c '.[]')
                fi

                # 2. Function to trace a folder's UID all the way up to the root
                get_full_path() {
                    local uid="$1"
                    local full_path=""
                    
                    while [ -n "$uid" ] && [ "$uid" != "null" ]; do
                        local current_title="${FOLDER_TITLE_MAP[$uid]}"
                        local parent_uid="${FOLDER_PARENT_MAP[$uid]}"
                        
                        if [ -n "$current_title" ]; then
                            if [ -z "$full_path" ]; then
                                full_path="$current_title"
                            else
                                full_path="$current_title/$full_path"
                            fi
                        fi
                        
                        uid="$parent_uid"
                    done
                    
                    echo "$full_path"
                }

                echo "========================================================="
                echo "Fetching dashboard metadata and backing up..."
                echo "========================================================="
                
                SEARCH_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/search?type=dash-db")

                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | jq -r '.uid')
                    TITLE=$(echo "$dash" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER_UID=$(echo "$dash" | jq -r '.folderUid // empty')
                    
                    # 3. Determine the full nested folder path
                    if [ -z "$FOLDER_UID" ] || [ "$FOLDER_UID" == "null" ]; then
                        FULL_FOLDER_PATH="General"
                    else
                        FULL_FOLDER_PATH=$(get_full_path "$FOLDER_UID")
                        
                        # Fallback just in case the folder map missed something
                        if [ -z "$FULL_FOLDER_PATH" ]; then
                            FULL_FOLDER_PATH=$(echo "$dash" | jq -r '.folderTitle' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                        fi
                    fi

                    # Fetch full dashboard data
                    FULL_DASH=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                        "$GRAFANA_URL/api/dashboards/uid/$DASH_UID")

                    UPDATED_AT=$(echo "$FULL_DASH" | jq -r '.meta.updated // empty')
                    
                    if [ -n "$UPDATED_AT" ]; then
                        UPDATED_EPOCH=$(date -d "${UPDATED_AT}" +%s 2>/dev/null || date -f - "${UPDATED_AT}" +%s 2>/dev/null)
                        THIRTY_DAYS_AGO=$(date -d '30 days ago' +%s)

                        if [ "$UPDATED_EPOCH" -gt "$THIRTY_DAYS_AGO" ]; then
                            echo "Skipping active dashboard: $FULL_FOLDER_PATH / $TITLE"
                            continue
                        fi
                    fi

                    # 4. Save the dashboard using the fully resolved nested path
                    mkdir -p "$BACKUP_DIR/$FULL_FOLDER_PATH"
                    echo "--> BACKING UP: $FULL_FOLDER_PATH / $TITLE ($DASH_UID)"
                    
                    echo "$FULL_DASH" | jq '.dashboard | .id = null' > "$BACKUP_DIR/$FULL_FOLDER_PATH/${TITLE}_${DASH_UID}.json"
                        
                done
                
                echo "Dashboard extraction complete."
                '''
            }
        }
        
        stage('Commit to Git') {
            steps {
                withCredentials([usernamePassword(credentialsId: '370af9a5-4d10-4db5-8f4a-4ef5411d1d7e', passwordVariable: 'GIT_PASSWORD', usernameVariable: 'GIT_USERNAME')]) {
                    sh '''#!/bin/bash
                    
                    git config user.name "Jenkins Backup Bot"
                    git config user.email "jenkins@your-company.com"
                    
                    if [ -d "dashboard_not_viewed_in_30_days" ]; then
                        git add dashboard_not_viewed_in_30_days/
                    fi
                    
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
