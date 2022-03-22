import jwt from 'jsonwebtoken';
import { openDB, deleteDB, wrap, unwrap } from 'idb';

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

async function getAuthentication() {
    let _jwtPublicKey = null;
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

    // Set up the IDB
    const db = await openDB('Webatrice');
    const transaction = db.transaction(['hosts', 'settings'], 'readwrite');
    
    function getOrCreateObjectStore(name, keyPath) {
        try {
            return transaction.objectStore(name);
        }catch(e) {
            return db.createObjectStore(name, { keyPath: keyPath });
        }
    }
    
    // Configure settings
    const hostStore = getOrCreateObjectStore('hosts', 'id');
    hostStore.clear();
    hostStore.add(settings);

    const settingStore = getOrCreateObjectStore('settings', 'user');
    settingStore.clear();
    settingStore.add({
        user:           '*app',
        autoConnect:    true,
    });
}

async function loadWebatrice() {
    await storeAuthentication();
    //await new Promise((resolve, reject) => setTimeout(resolve, 1));

    const elm = document.getElementById('webatrice');
    elm.setAttribute('src', `/webatrice/index.html`);
}

console.log('loaded game.js');
loadWebatrice();


