npm install
composer install
# composer run-script update-fontawesome
rm -fR ./public/dist/ 
npx webpack --config webpack.config.js --mode production