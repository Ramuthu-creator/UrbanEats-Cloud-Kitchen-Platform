#!/usr/bin/env bash
# Sync workspace changes to XAMPP htdocs
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="/Applications/XAMPP/xamppfiles/htdocs/urbaneats"

echo "Syncing UrbanEats to XAMPP htdocs..."
rsync -av --delete --exclude '.git' "$SOURCE_DIR/" "$TARGET_DIR/"
echo "✓ Sync complete! Live at http://localhost/urbaneats"
