#!/bin/bash

# Exit on error
set -e

# Store the current directory
CURRENT_DIR=$(pwd)

# Function to handle npm operations in a directory
handle_npm_operations() {
    local dir=$1
    echo "Processing $dir..."
    if [ -d "$dir" ]; then
        cd "$dir"
        if [ -f "package.json" ]; then
            echo "Installing dependencies in $dir..."
            npm install
            echo "Building $dir..."
            npm run build
        else
            echo "No package.json found in $dir"
        fi
        cd "$CURRENT_DIR"
    else
        echo "Directory $dir not found"
    fi
}

# Process inspector panels
handle_npm_operations "includes/bylines-inspector-sidebar-panel"
handle_npm_operations "includes/staff-inspector-sidebar-panel"

# Process blocks directory
handle_npm_operations "blocks"

echo "Build process completed!"
