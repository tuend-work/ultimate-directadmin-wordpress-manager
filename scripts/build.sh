#!/bin/bash
# build.sh - Build SUID wrappers manually via SSH root terminal

PLUGIN_DIR="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager"

echo "Building SUID wrappers..."

if [ -f "$PLUGIN_DIR/scripts/wrapper.c" ]; then
    gcc -O2 "$PLUGIN_DIR/scripts/wrapper.c" -o "$PLUGIN_DIR/scripts/wrapper"
    chown root:diradmin "$PLUGIN_DIR/scripts/wrapper"
    chmod 4755 "$PLUGIN_DIR/scripts/wrapper"
    echo "-> wrapper SUID built."
fi

if [ -f "$PLUGIN_DIR/scripts/update_wrapper.c" ]; then
    gcc -O2 "$PLUGIN_DIR/scripts/update_wrapper.c" -o "$PLUGIN_DIR/scripts/update_wrapper"
    chown root:diradmin "$PLUGIN_DIR/scripts/update_wrapper"
    chmod 4755 "$PLUGIN_DIR/scripts/update_wrapper"
    echo "-> update_wrapper SUID built."
fi

echo "All wrappers built successfully!"
