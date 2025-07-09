#!/bin/bash

# Deployment script for Free Tonight app
# 
# For NearlyFreeSpeech.net (default):
#   ./deploy.sh
#   ./deploy.sh ssh.nyc1.nearlyfreespeech.net your-username
#
# For other hosting services, modify the variables below:
#   ./deploy.sh your-server.com your-username

# Default NearlyFreeSpeech.net settings
DEFAULT_SERVER="ssh.nyc1.nearlyfreespeech.net"
DEFAULT_USERNAME="your-username"
DEFAULT_PUBLIC_PATH="/home/public/freetonight/"
DEFAULT_PRIVATE_PATH="/home/private/freetonight/"

# Use provided arguments or defaults
SERVER=${1:-$DEFAULT_SERVER}
USERNAME=${2:-$DEFAULT_USERNAME}
PUBLIC_PATH=${3:-$DEFAULT_PUBLIC_PATH}
PRIVATE_PATH=${4:-$DEFAULT_PRIVATE_PATH}

# Check if this is a setup run
if [ "$1" = "--setup" ]; then
    echo "Setting up directories and permissions..."
    echo "Server: $SERVER"
    echo "Username: $USERNAME"
    echo "Private path: $PRIVATE_PATH"
    echo ""
    
    echo "1. Creating private directory on server..."
    ssh $USERNAME@$SERVER "mkdir -p $PRIVATE_PATH"
    
    echo "2. Setting permissions..."
    ssh $USERNAME@$SERVER "chmod 755 $PRIVATE_PATH"
    
    echo "✅ Setup complete!"
    echo "Now you can run ./deploy.sh for normal deployments."
    exit 0
fi

echo "Deploying Free Tonight app..."
echo "Server: $SERVER"
echo "Username: $USERNAME"
echo "Target: $USERNAME@$SERVER:$PUBLIC_PATH"
echo ""
echo "Note: Skipping directory creation and permissions (already set up)"
echo "Run './deploy.sh --setup' if you need to recreate directories"
echo ""

# Check if files exist
if [ ! -d "public/freetonight" ]; then
    echo "Error: public/freetonight directory not found!"
    exit 1
fi

echo "Uploading public files using rsync..."
rsync -avz --delete \
  --exclude='.DS_Store' \
  --exclude='deploy.sh' \
  --exclude='*.log' \
  --exclude='*.db' \
  public/freetonight/ \
  $USERNAME@$SERVER:$PUBLIC_PATH

echo ""
echo "✅ Deployment complete!"
echo ""
echo "Next steps:"
echo "1. Configure config.php with your domain name"
echo "2. Test the application at your domain"
echo "3. Check error logs if needed"
