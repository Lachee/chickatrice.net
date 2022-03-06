import bulmaCollapsible from '@creativebulma/bulma-collapsible';

// self executing function here
document.addEventListener('DOMContentLoaded', () => {
    // your page initialization code here
    // the DOM will be available here
 
    // Setup the burger menu
    document.querySelector('.navbar-burger').addEventListener('click', (event) => {
        if (event.target.classList.contains('is-active')) {
            event.target.classList.remove('is-active');
            document.querySelectorAll('.navbar-menu').forEach((elm) => {
                elm.classList.remove('is-active');
            });
        } else {            
            event.target.classList.add('is-active');
            document.querySelectorAll('.navbar-menu').forEach((elm) => {
                elm.classList.add('is-active');
            });
        }
    });

    // Return an array of bulmaCollapsible instances (empty if no DOM node found)
    const bulmaCollapsibleInstances = bulmaCollapsible.attach('.is-collapsible');

    // Loop into instances
    bulmaCollapsibleInstances.forEach(bulmaCollapsibleInstance => {
    });
 }, false);
