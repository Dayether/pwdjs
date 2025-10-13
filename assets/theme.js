// Add subtle shadow when page is scrolled
(function(){
	const nav = document.querySelector('.navbar-themed');
	if (!nav) return;
	const onScroll = () => {
		if (window.scrollY > 4) nav.classList.add('is-scrolled');
		else nav.classList.remove('is-scrolled');
	};
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
})();
