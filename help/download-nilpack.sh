#!/bin/bash

set -e

REPO="nilpack"
OWNER="${OWNER:-$(basename $(dirname $(git remote get-url origin)))}"
TAG="${TAG:-latest}"

if [ "$TAG" = "latest" ]; then
    TAG=$(curl -s https://api.github.com/repos/$OWNER/$REPO/releases/latest | grep '"tag_name"' | sed -E 's/.*"([^"]+)".*/\1/')
fi

echo "Downloading nilpack.phar from release $TAG..."

curl -L -o nilpack.phar "https://github.com/$OWNER/$REPO/releases/download/$TAG/nilpack.phar"

chmod +x nilpack.phar

echo "Download complete: nilpack.phar"