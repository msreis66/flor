(() => {
  const header = document.querySelector('header')

  if (header) {
    // Toggle sticky class on window scroll event.
    window.addEventListener('scroll', () => header.classList.toggle('sticky-lg-top', window.scrollY > 0))
  }
})()
