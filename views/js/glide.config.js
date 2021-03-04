document.addEventListener('DOMContentLoaded', function() {

  function glideInit() {
    var glide = new Glide('#glide', {
      perView: 3,
      hoverpause: true,
      type: 'carousel',
      autoplay: 2000,
      breakpoints: {
        1024: {
          perView: 2
        }
      }
    })
    glide.mount();
  }
  glideInit();
  prestashop.on('updateProductList', () => {
    glideInit();
  });

}, false);
