((Drupal, once) => {
  document.addEventListener('DOMContentLoaded', (event) => {
    document.querySelectorAll('.bootstrap-ui-kit .modal code').forEach((el) => {
      hljs.highlightElement(el);
      hljs.addPlugin(new CopyButtonPlugin());
    });
  });
})(Drupal, once);
