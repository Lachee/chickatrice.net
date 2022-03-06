echo '=== Updating Code';
git pull

echo '=== Updating Packages';
npm install
composer install

echo '=== Updating Styles';
./pack.sh

echo 'DONE';