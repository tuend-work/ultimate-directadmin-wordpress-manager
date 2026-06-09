#!/bin/sh
# Set correct permissions for scripts and entry points
PLUGIN_DIR="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager"

chmod 755 "$PLUGIN_DIR"/scripts/*.sh
chmod 755 "$PLUGIN_DIR"/admin/index.html
chmod 755 "$PLUGIN_DIR"/admin/index.raw
chmod 755 "$PLUGIN_DIR"/reseller/index.html
chmod 755 "$PLUGIN_DIR"/reseller/index.raw
chmod 755 "$PLUGIN_DIR"/user/index.html
chmod 755 "$PLUGIN_DIR"/user/index.raw

# Compile wrapper.c as SUID root
if [ -f "$PLUGIN_DIR/scripts/wrapper.c" ]; then
  gcc -O2 "$PLUGIN_DIR/scripts/wrapper.c" -o "$PLUGIN_DIR/scripts/wrapper"
  if [ -f "$PLUGIN_DIR/scripts/wrapper" ]; then
    chown root:diradmin "$PLUGIN_DIR/scripts/wrapper"
    chmod 4755 "$PLUGIN_DIR/scripts/wrapper"
  fi
fi

exit 0
