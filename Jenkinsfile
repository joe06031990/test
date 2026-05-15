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
                // Check out the master branch where the backups will be saved
                git branch: 'master', url: "${GIT_REPO_URL}"
            }
        }

        stage('Backup Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e 

                BACKUP_DIR="dashboards"
                mkdir -p "$BACKUP_DIR"

                echo "Fetching dashboard metadata from Grafana API..."
                
                SEARCH_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    "$GRAFANA_URL/api/search?type=dash-db")

                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    DASH_UID=$(echo "$dash" | jq -r '.uid')
                    TITLE=$(echo "$dash" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER=$(echo "$dash" | jq -r '.folderTitle' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    
                    if [ "$FOLDER" == "null" ] || [ -z "$FOLDER" ]; then
                        FOLDER="General"
                    fi

                    mkdir -p "$BACKUP_DIR/$FOLDER"
                    
                    echo "Exporting: $FOLDER / $TITLE ($DASH_UID)"
                    
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
                    
                    # Add all JSON files in the dashboards directory
                    git add dashboards/
                    
                    # Check if there are actually any changes to commit
                    if git diff-index --quiet HEAD --; then
                        echo "No dashboard changes detected. Skipping commit."
                    else
                        git commit -m "Automated Grafana Backup: $(date +'%Y-%m-%d')"
                        
                        # Push using the securely injected GitHub credentials
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
