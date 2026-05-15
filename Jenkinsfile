pipeline {
    agent any
    
    environment {
        // Your specific Grafana and Git variables
        GRAFANA_TOKEN = credentials('GRAFANA_API_KEY')
        GRAFANA_URL   = 'https://jstest2025.grafana.net'
        GIT_REPO_URL  = 'https://github.com/joe06031990/test'
    }

    stages {
        stage('Clone Backup Repo') {
            steps {
                // Checks out the main branch of your GitHub repo
                git branch: 'main', url: "${GIT_REPO_URL}"
            }
        }

        stage('Backup Dashboards via Bash') {
            steps {
                sh '''#!/bin/bash
                set -e # Exit immediately if a command exits with a non-zero status

                BACKUP_DIR="dashboards"
                mkdir -p "$BACKUP_DIR"

                echo "Fetching dashboard metadata from Grafana API..."
                
                # Fetch all items of type 'dash-db'
                SEARCH_RESP=$(curl -s -f -H "Authorization: Bearer $GRAFANA_TOKEN" \
                    "$GRAFANA_URL/api/search?type=dash-db")

                # Iterate through each dashboard using jq
                echo "$SEARCH_RESP" | jq -c '.[]' | while read -r dash; do
                    
                    # Extract variables
                    DASH_UID=$(echo "$dash" | jq -r '.uid')
                    TITLE=$(echo "$dash" | jq -r '.title' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    FOLDER=$(echo "$dash" | jq -r '.folderTitle' | sed -e 's/[^A-Za-z0-9._-]/_/g')
                    
                    # If dashboard is in the root, the folder API returns "null"
                    if [ "$FOLDER" == "null" ] || [ -z "$FOLDER" ]; then
                        FOLDER="General"
                    fi

                    mkdir -p "$BACKUP_DIR/$FOLDER"
                    
                    echo "Exporting: $FOLDER / $TITLE ($DASH_UID)"
                    
                    # Fetch the actual dashboard JSON and extract just the 'dashboard' object
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
                    git push origin main
                    echo "Successfully pushed updates to GitHub."
                fi
                '''
            }
        }
    }
    
    post {
        always {
            cleanWs()
        }
    }
}
