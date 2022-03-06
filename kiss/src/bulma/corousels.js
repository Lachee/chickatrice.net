import bulmaCarousel from 'bulma-carousel/src/js/';

// self executing function here
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all elements with carousel class.
    const carousels = bulmaCarousel.attach('.carousel', {
    });

    // To access to bulmaCarousel instance of an element
    const element = document.querySelector('#my-element');
    if (element && element.bulmaCarousel) {
        // bulmaCarousel instance is available as element.bulmaCarousel
    }
 }, false);
