# Process isolation

The Discord gateway runs as its own Node process/service. A Discord reconnect/crash therefore does not need to crash PHP-FPM or the Nodexa web panel. systemd can restart the bot independently while admins retain access to configuration.
