#!/usr/bin/env bash
# macOS: دوبار کلیک.
cd "$(dirname "$0")/../.." || exit 1
exec node tools/licensing/ui/server.js
