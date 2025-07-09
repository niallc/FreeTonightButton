#!/bin/bash

# Script to enable debug logging for troubleshooting
# Usage: ./enable_debug_logging.sh [duration_in_minutes]

DURATION=${1:-5}  # Default to 5 minutes

echo "🔧 Enabling debug logging for $DURATION minutes..."
echo "This will show detailed logs for all API requests."
echo ""

# Set environment variable for debug logging
export FREETONIGHT_LOG_LEVEL=DEBUG

echo "✅ Debug logging enabled!"
echo "📝 Log level set to: DEBUG"
echo "⏰ Will automatically disable in $DURATION minutes"
echo ""
echo "💡 To manually disable: unset FREETONIGHT_LOG_LEVEL"
echo "💡 To check current level: echo \$FREETONIGHT_LOG_LEVEL"
echo ""

# Auto-disable after specified duration
if [ "$DURATION" -gt 0 ]; then
    echo "⏰ Auto-disabling debug logging in $DURATION minutes..."
    (
        sleep $((DURATION * 60))
        unset FREETONIGHT_LOG_LEVEL
        echo ""
        echo "🔄 Debug logging automatically disabled"
        echo "📝 Log level reset to default"
    ) &
fi

echo "🚀 Your dev server will now show detailed logs!"
echo "📊 Make some API requests to see the debug output." 