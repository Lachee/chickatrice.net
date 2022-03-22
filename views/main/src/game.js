import jwt from 'jsonwebtoken';

const MTG_SERVER = 'mtg.chickatrice.net';
const HOST_SETTINGS = {
    id:             1,
    name:           'Chickatrice',
    host:           MTG_SERVER + '/servatrice',
    post:           '443',
    editable:       false,
    userName:       '',
    hashedPassword: '',
    remember:       true,
    lastSelected:   true,
}

let _jwtPublicKey = null;
async function getAuthentication() {
    if (_jwtPublicKey == null) {
        const response = await fetch('/jwt', {
            method: 'POST',
            mode: 'same-origin',
        });
        const payload = await response.json();
        _jwtPublicKey = payload.data.publicKey;
    }

    const token = kiss.get('authentication');
    const authentication = jwt.verify(token, _jwtPublicKey);
    
    const decoded = atob(authentication.auth).split(':', 3);
    if (decoded.length != 3) throw new Error('decoded data is not of correct length');
    if (decoded[1] != authentication.name) throw new Error('authetnication does not match');

    const name = authentication.name;
    const hash = decoded[2];
    return { name, hash };
}

async function storeAuthentication() {
    // Fetch and apply the auth to the settings
    const { name, hash } = await getAuthentication();
    const settings = Object.assign({}, HOST_SETTINGS);
    settings.userName = name;
    settings.hashedPassword = hash;

    await new Promise((resolve, reject) => {            
        // Set up the IDB
        const request = indexedDB.open('Webatrice', 10);
        request.onerror = event => {
            console.error('Fialed to connected to IDB', event);
            reject(event);
        };
        
        // Create new DB
        // request.onupgradeneeded = event => {
        //     console.log('upgrading connection');
        //     const db = event.target.result;
        //     db.createObjectStore('hosts', { keyPath: 'id' });
        //     db.createObjectStore('settings', { keyPath: 'user' });
        // };
        
        // Setup auto connect
        request.onsuccess = event => {
            try {
                const db = event.target.result;                    
                const transaction = db.transaction(['hosts', 'settings'], 'readwrite');
                const hostStore = transaction.objectStore('hosts');
                hostStore.clear();
                hostStore.add(settings);
            
                const settingStore = transaction.objectStore('settings');
                settingStore.clear();
                settingStore.add({
                    user:           '*app',
                    autoConnect:    true,
                });

                resolve();
            }catch(error) {
                reject(error);
            }
        };
    });
};


async function loadWebatrice() {

    const statusElement = document.getElementById('webatrice-status');
    const clientElement = document.getElementById('webatrice');
    clientElement.style.display = 'none';
    
    let allowReload = true;

    statusElement.textContent = 'Loading Client...';
    clientElement.setAttribute('src', `/webatrice/index.html`);
    clientElement.addEventListener('load',() => {
        statusElement.textContent = 'Connecting...';
        setTimeout(async () => {
            await storeAuthentication();
            if (allowReload) {
                // We authed, so reload the page to auto connect
                statusElement.textContent = 'Authenticating...';
                window.onbeforeunload = {};
                clientElement.setAttribute('src', `/webatrice/index.html`);
                allowReload = false;
            } else {
                // Join as normal
                statusElement.textContent = 'Joining...';
                setTimeout(() => {
                    clientElement.style.display = 'unset';
                }, 2500);
            }
        }, 10);
    });
    return;

    try 
    {   
        await storeAuthentication();
        clientElement.setAttribute('src', `/webatrice/index.html`);
    }
    catch (error) 
    {
        console.warn('Error while loading: ', error);
        let allowReload = true;
        clientElement.setAttribute('src', `/webatrice/index.html`);
        clientElement.addEventListener('load',() => {
            setTimeout(async () => {
                await storeAuthentication();
                if (allowReload) {
                    console.log('reloading...');
                    clientElement.setAttribute('src', `/webatrice/index.html`);
                    allowReload = false;
                }
            }, 100);
        });
    }

}

console.log('loaded game.js');
loadWebatrice();


