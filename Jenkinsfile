pipeline {
    agent any
    
    triggers {
        cron('0 1 1 * *')
    }

    environment {
        GRAFANA_TOKEN = credentials('GRAFANA_API_KEY')
        GRAFANA_URL   = 'https://jstest2025.grafana.net'
        GIT_REPO_URL  = 'https://github.com/joe06031990/test'
        GIT_BRANCH    = 'master' 
}
    stages {
        stage('Clone Backup Repo') {
            steps {
                git branch: "${GIT_BRANCH}", url: "${GIT_REPO_URL}"
            }
        }

        stage('Backup Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e 

                BACKUP_DIR="dashboard_not_viewed_in_30_days_non_prd"
                mkdir -p "$BACKUP_DIR"

                echo "========================================================="
                echo "Building Grafana nested folder hierarchy map..."
                echo "========================================================="
                
                declare -A FOLDER_TITLE_MAP
                declare -A FOLDER_PARENT_MAP

                FOLDERS_RESP=$(curl -s -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/search?type=dash-folder")
                
                if [ "$(echo "$FOLDERS_RESP" | jq -r 'type' 2>/dev/null)" == "array" ]; then
                    while read -r folder; do
                        f_uid=$(echo "$folder" | jq -r '.uid // empty')
                        f_title=$(echo "$folder" | jq -r '.title // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                        f_parent=$(echo "$folder" | jq -r '.folderUid // empty')
                        
                        if [ -n "$f_uid" ]; then
                            FOLDER_TITLE_MAP["$f_uid"]="$f_title"
                            FOLDER_PARENT_MAP["$f_uid"]="$f_parent"
                        fi
                    done < <(echo "$FOLDERS_RESP" | jq -c '.[]')
                else
                    echo "WARNING: Folder API failed. Expected an array but got:"
                    echo "$FOLDERS_RESP"
                fi

                echo "========================================================="
                echo "Fetching dashboard metadata and backing up..."
                echo "========================================================="
                
                SEARCH_RESP=$(curl -s -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/search?type=dash-db")

                if [ "$(echo "$SEARCH_RESP" | jq -r 'type' 2>/dev/null)" != "array" ]; then
                    echo "ERROR: Dashboard API request failed. Expected an array but got:"
                    echo "$SEARCH_RESP"
                    exit 1
                fi

                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | jq -r '.uid // empty')
                    TITLE=$(echo "$dash" | jq -r '.title // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER_UID=$(echo "$dash" | jq -r '.folderUid // empty')
                    
                    FULL_FOLDER_PATH=""

                    if [ -n "$FOLDER_UID" ] && [ "$FOLDER_UID" != "null" ]; then
                        curr_uid="$FOLDER_UID"
                        loop_guard=0
                        
                        while [ -n "$curr_uid" ] && [ "$curr_uid" != "null" ]; do
                            curr_title="${FOLDER_TITLE_MAP[$curr_uid]}"
                            parent_uid="${FOLDER_PARENT_MAP[$curr_uid]}"

                            if [ -n "$curr_title" ]; then
                                if [ -z "$FULL_FOLDER_PATH" ]; then
                                    FULL_FOLDER_PATH="$curr_title"
                                else
                                    FULL_FOLDER_PATH="$curr_title/$FULL_FOLDER_PATH"
                                fi
                            else
                                break
                            fi
                            
                            curr_uid="$parent_uid"
                            
                            loop_guard=$((loop_guard + 1))
                            if [ $loop_guard -gt 20 ]; then break; fi
                        done
                    fi

                    if [ -z "$FULL_FOLDER_PATH" ]; then
                        fallback_title=$(echo "$dash" | jq -r '.folderTitle // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                        if [ -n "$fallback_title" ] && [ "$fallback_title" != "null" ]; then
                            FULL_FOLDER_PATH="$fallback_title"
                        else
                            FULL_FOLDER_PATH="General"
                        fi
                    fi

                    FULL_DASH=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/dashboards/uid/$DASH_UID" || echo "")

                    if [ -n "$FULL_DASH" ]; then
                        UPDATED_AT=$(echo "$FULL_DASH" | jq -r '.meta.updated // empty')
                        
                        if [ -n "$UPDATED_AT" ]; then
                            UPDATED_EPOCH=$(date -d "${UPDATED_AT}" +%s 2>/dev/null || date -f - "${UPDATED_AT}" +%s 2>/dev/null)
                            THIRTY_DAYS_AGO=$(date -d '30 days ago' +%s)

                            if [ "$UPDATED_EPOCH" -gt "$THIRTY_DAYS_AGO" ]; then
                                echo "Skipping active dashboard: $FULL_FOLDER_PATH / $TITLE"
                                continue
                            fi
                        fi

                        mkdir -p "$BACKUP_DIR/$FULL_FOLDER_PATH"
                        echo "--> BACKING UP: $BACKUP_DIR/$FULL_FOLDER_PATH / $TITLE ($DASH_UID)"
                        
                        echo "$FULL_DASH" | jq '.dashboard | .id = null' > "$BACKUP_DIR/$FULL_FOLDER_PATH/${TITLE}_${DASH_UID}.json"
                    else
                        echo "WARNING: Could not fetch JSON for dashboard UID: $DASH_UID"
                    fi
                        
                done
                
                echo "Dashboard extraction complete."
                '''
            }
        }
        
        stage('Commit to Git') {
            steps {
                withCredentials([usernamePassword(credentialsId: '370af9a5-4d10-4db5-8f4a-4ef5411d1d7e', passwordVariable: 'GIT_PASSWORD', usernameVariable: 'GIT_USERNAME')]) {
                    sh '''#!/bin/bash
                    
                    git config user.name "Jenkins Backup"
                    git config user.email "jenkins"
                    
                    if [ -d "dashboard_not_viewed_in_30_days_non_prd" ]; then
                        git add dashboard_not_viewed_in_30_days_non_prd/
                    fi
                    
                    if git diff-index --quiet HEAD --; then
                        echo "No new stale dashboards detected or created. Skipping commit."
                    else
                        git commit -m "Automated Grafana Stale Dashboard Backup: $(date +'%Y-%m-%d')"
                        
                        git push https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/joe06031990/test.git ${GIT_BRANCH}
                       
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
