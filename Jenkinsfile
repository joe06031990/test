pipeline {
    agent any
    triggers {
        // Run automatically at the start of every hour (e.g., 0:00, 1:00, 2:00, etc.)
        cron('0 * * * *')
    }
    environment {
        GRAFANA_TOKEN = credentials('GRAFANA_API_KEY')
        GRAFANA_URL   = 'https://jstest2025.grafana.net'
        GIT_REPO_URL  = 'https://github.com/joe06031990/test'
        // Hardcode the branch name because env.BRANCH_NAME is null in standard pipelines
        GIT_BRANCH    = 'master'
    }
    stages {
        stage('Backup Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e 
                
                BACKUP_DIR="dashboard_not_viewed_in_30_days_non_prd"
                # Define the specific folder you want to target
                TARGET_FOLDER="playground"
                
                mkdir -p "$BACKUP_DIR"
                
                # 1. Determine architecture and download the correct standalone jq binary
                ARCH=$(uname -m)
                echo "Detected node architecture: $ARCH"
                
                if [ "$ARCH" = "x86_64" ]; then
                    JQ_BIN="jq-linux64"
                elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then
                    JQ_BIN="jq-linux-arm64"
                elif [ "$ARCH" = "i686" ]; then
                    JQ_BIN="jq-linux32"
                else
                    echo "ERROR: Unsupported architecture ($ARCH) for standalone jq download."
                    exit 1
                fi

                echo "Downloading standalone $JQ_BIN..."
                curl -L -s -o ./jq "https://github.com/jqlang/jq/releases/download/jq-1.7.1/$JQ_BIN"
                chmod +x ./jq
                
                # Test the binary immediately to fail fast if it's still incompatible
                ./jq --version
                
                echo "========================================================="
                echo "Building Grafana nested folder hierarchy map..."
                echo "========================================================="
                
                declare -A FOLDER_TITLE_MAP
                declare -A FOLDER_PARENT_MAP
                FOLDERS_RESP=$(curl -s -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/search?type=dash-folder")
                
                # Check for error message in response (safe for set -e)
                if echo "$FOLDERS_RESP" | grep -q '"message":'; then
                    echo "WARNING: Folder API returned an error message. Processing may be incomplete."
                fi
                
                # Process folders using the local ./jq binary
                while read -r folder; do
                    f_uid=$(echo "$folder" | ./jq -r '.uid // empty')
                    f_title=$(echo "$folder" | ./jq -r '.title // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    f_parent=$(echo "$folder" | ./jq -r '.folderUid // empty')
                    
                    if [ -n "$f_uid" ]; then
                        FOLDER_TITLE_MAP["$f_uid"]="$f_title"
                        FOLDER_PARENT_MAP["$f_uid"]="$f_parent"
                    fi
                done < <(echo "$FOLDERS_RESP" | ./jq -c '.[]')
                
                echo "========================================================="
                echo "Fetching dashboard metadata and filtering for '$TARGET_FOLDER'..."
                echo "========================================================="
                
                SEARCH_RESP=$(curl -s -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/search?type=dash-db")
                
                # Handle large JSON payloads safely (safe for set -e)
                if [ -z "$SEARCH_RESP" ]; then
                    echo "ERROR: Dashboard API request returned empty."
                    exit 1
                fi
                if echo "$SEARCH_RESP" | grep -q '"message":'; then
                    echo "ERROR: Dashboard API request failed or returned unauthorized."
                    echo "$SEARCH_RESP"
                    exit 1
                fi
                
                # Pipe response directly to ./jq to handle high dashboard counts
                echo "$SEARCH_RESP" | ./jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | ./jq -r '.uid // empty')
                    TITLE=$(echo "$dash" | ./jq -r '.title // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER_UID=$(echo "$dash" | ./jq -r '.folderUid // empty')
                    
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
                        fallback_title=$(echo "$dash" | ./jq -r '.folderTitle // empty' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                        if [ -n "$fallback_title" ] && [ "$fallback_title" != "null" ]; then
                            FULL_FOLDER_PATH="$fallback_title"
                        else
                            FULL_FOLDER_PATH="General"
                        fi
                    fi

                    # --- NEW FILTERING LOGIC ---
                    # Skip if the path is not exactly "playground" AND doesn't start with "playground/"
                    if [[ "$FULL_FOLDER_PATH" != "$TARGET_FOLDER" ]] && [[ "$FULL_FOLDER_PATH" != "$TARGET_FOLDER"/* ]]; then
                        continue
                    fi
                    # ---------------------------
                    
                    FULL_DASH=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" "$GRAFANA_URL/api/dashboards/uid/$DASH_UID" || echo "")
                    
                    if [ -n "$FULL_DASH" ]; then
                        # Create folder structures locally and back up all retrieved dashboards
                        mkdir -p "$BACKUP_DIR/$FULL_FOLDER_PATH"
                        echo "--> BACKING UP: $BACKUP_DIR/$FULL_FOLDER_PATH / $TITLE ($DASH_UID)"
                        echo "$FULL_DASH" | ./jq '.dashboard | .id = null' > "$BACKUP_DIR/$FULL_FOLDER_PATH/${TITLE}_${DASH_UID}.json"
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
                        echo "No dashboard changes detected. Skipping commit."
                    else
                        git commit -m "Automated Grafana Dashboard Backup: $(date +'%Y-%m-%d %H:%M:%S')"
                        git push "https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/joe06031990/test.git" HEAD:${GIT_BRANCH}
                        echo "Successfully pushed updates to GitHub."
                    fi
                    '''
                }
            }
        }
    }
    post {
        always {
            // Native shell cleanup to bypass missing Jenkins plugin error
            sh 'rm -rf * .??*'
        }
    }
}
