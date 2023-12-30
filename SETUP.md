
# KISS Setup Instructions

  

This guide aims to take you through the process of setting up your GALL instance.
While custom instances are not officially supported (defeats its purpose), its important for testing reasons.

Before we begin, please ensure you have all the dependencies installed:

### Dependencies
* PHP +.2
* NPM ( Node 16 )
* Composer
* Redis
* MySQL (or mariadb)
* GIT
* PHP extension `gmp`. Likely already installed but just enable it. Its **only** used for discord interaction.

now that the dependencies are sorted, lets get down to business.

## 1. Download Files
This is very stupidly easy. Just git clone the repository.

## 2. Install Packages
KISS framework uses 2 package managers... horrible isn't. It uses composer for its PHP backend and NPM for its JS front end (with Webpack and babel transpiling). 

First, install the node packages. This will likely cause you the most issue because of Font Awesome 5 Pro. Some adjustments maybe required (still working on it, suggestions pls)
`npm install`
`npm install --save --legacy-peer-deps` if issues occur.

Congrats on getting the node packages installed! Now, install the composer packages.
`composer install`

## 3. Build Packages
Now for the hard part with the packages. We need to pack the webpack. 
**You will need to do this every time you update a .JS file or .SCSS**

`rm -rR ./public/dist/` - Clears the cache of any left overs
`npx webpack --config webpack.config.json --mode production` - Builds the pack

or,,, you could just run `./pack.sh` or `./pack-watch.sh` to build the packs (later is useful while developing).

## 4. Configure
Configuration is a massive step, so I will break it down into smaller steps.
But first, copy the configuration template.
`cp config-sample.php config.php`

now open up your newly created `config.php`. Its a mess i know. But please note the DB, JWT and Discord parts, we will be editing those.

But to begin with, update the `define('BASE_URL', 'http://gall.local:81/');` to match your URL instead (like `localhost` or something).

### 4.1 Configure DB
1. Download the DB schema from me. I am is always editing this so its likely you are going to need a fresh copy. Import this into your database.
2. Update the DB array to match your details
	* dsn is the connection string. Just modify existing string unless you know how these work.
	* user is the username
	* pass is the password
	* prefix is the prefix of all the tables. If you did a plain import, leave this as.
 
### 4.2 Configure Redis
This is really simple, just modify the client object directly to match your settings. Predis can be found here https://github.com/predis/predis

### 4.3 Configure JWT
KISS uses JWTs for fucking everything. Session, api, csrf tokens, the lot. Its important you spend the time configuring the JWT. You can use a cheaper variant if you wish, but ALGO_RS512 (512 bytes) works for me.

They are just an RSA pair, so generate them how you like, but one super lazy approach is to use this website https://travistidwell.com/jsencrypt/demo/

Just stick the keys in the files listed: `jwt-private.key` and `jwt-public.key`
(they are technically loaded everytime, so you could put them wherever the fuck you want and update the config)

### 4.4 Configure Discord
No, im not giving you my bot token.
You will need to configure both and oauth and bot client.
1. Open the developer portal
2. Create a new application (or modify existing)
3. Copy `Client ID` to the `clientId` in the config
4. Copy `Client Secret` to the `clientSecret`
5. Go to the oAuth2 page and add `{baseurl}/auth` to the allowed redirects (where `{baseurl}` is the site's root url configured at the start)
6. Create a bot and copy it's token to `botToken`. This probably isn't required at the moment, since it is only for adding images without an owner.

## 5. Profit!
Everything is setup now. So time for a quick lesson. 
* Everything inherits `BaseObject`, and this provides some magic
* Components are hot-loaded into the main application class via the settings, see how Discord does it
* The public pages are actually `./public/index.php`, so make sure you allow for `.htaccess` rewrite rules.
* Its a **MVC** system. Models are `/models`, views are `/views/<controller>/<action>.php`, and controllers are `/controllers`. 
* Page controllers define their actions so `/account/settings` is `AccountController::actionSettings`
* API controllers define their methods so `GET /account` is `AccountRoute::get`
* Each view has its own JS file that is hotloaded via webpack. Its safe to exclude these but they are in `/views/<controller>/src/<action>.js` and the special `_view.js` is loaded for every view in that controller.
* All the objects are actually based of JSON Schema rules, so thats neat.
* All the framework stuff is in `/kiss`, check that folder out for a bunch of insight on how it works. Particular files of note:
	* `Kiss.php` main class 
	* `/helpers/HTTP.php` handles HTTP variables
	* `/helpers/Response.php` everything returns a response, and KISS at the very end will determine how to output that response.
	* `/helpers/StringHelper.php` provides functionality that hasn't fucking come to php until fucking PHP 8 WHY DO I NEED TO WAIT 20 YEARS TO GET STR_STARTS_WITH.
	* `/helpers/ArrayHelper.php` provides cool dope functions like `map`
	* `/exception` everything throws an exception. If something goes wrong, throw, **dont try to recover**. Let the error handlers handle it. 
	* `/db` magic records stuff. Pretty cool beans.
	* `/schema` magic schema stuff. Pretty cool rocks.
	* `/src` contains the backend kiss framework. Actually gets compiled to a different js, so thats neat.


Anyways, thats it for my rant. Seriously thank you for wanting to contribute, and feel free to ask me dumb questions.
Cheers,
Lachee
