#!/bin/bash

# Deployment script for NearlyFreeSpeech.net
# Usage: ./deploy.sh [optional: custom server] [optional: custom username]
#        ./deploy.sh --setup (to create directories and set permissions)

# Default NearlyFreeSpeech.net settings
DEFAULT_SERVER="ssh.nyc1.nearlyfreespeech.net"
DEFAULT_USERNAME="niallcardin_niallhome"

# Use provided arguments or defaults
SERVER=${1:-$DEFAULT_SERVER}
USERNAME=${2:-$DEFAULT_USERNAME}

# Check if this is a setup run
if [ "$1" = "--setup" ]; then
    echo "Setting up directories and permissions..."
    echo "Server: $SERVER"
    echo "Username: $USERNAME"
    echo ""
    
    echo "1. Creating private directory on server..."
    ssh $USERNAME@$SERVER "mkdir -p /home/private/freetonight"
    
    echo "2. Setting permissions..."
    ssh $USERNAME@$SERVER "chmod 755 /home/private/freetonight"
    ssh $USERNAME@$SERVER "chmod 755 /home/private"
    
    echo "✅ Setup complete!"
    echo "Now you can run ./deploy.sh for normal deployments."
    exit 0
fi

echo "Deploying to NearlyFreeSpeech.net..."
echo "Server: $SERVER"
echo "Username: $USERNAME"
echo "Target: $USERNAME@$SERVER:/home/public/freetonight/"
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
  $USERNAME@$SERVER:/home/public/freetonight/

echo ""
echo "✅ Deployment complete!"
echo ""
