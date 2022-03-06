#!/bin/bash
echo "Launching PHP Server";
echo " -- While this script does exist, this isn't a supported usage";
cd public
export PHP_CLI_SERVER_WORKERS=10
php -S localhost:8080 router.php