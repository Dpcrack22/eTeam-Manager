/* Landing page interactions are handled through normal navigation. */

// Sidebar search autocomplete
(function(){
	const sidebarInput = document.getElementById('sidebar-search-input');
	const sidebarType = document.getElementById('sidebar-search-type');
	const sidebarSuggestions = document.getElementById('sidebar-search-suggestions');
	const pageInput = document.getElementById('page-search-input');
	const pageType = document.getElementById('page-search-type');
	const pageSuggestions = document.getElementById('page-search-suggestions');

	// choose which set is active based on where the event came from
	function bindAutocomplete(inputEl, typeEl, suggestionsEl) {
		if (!inputEl || !suggestionsEl) return;

	let timer = 0;

		function clearSuggestions(){
			suggestionsEl.innerHTML = '';
			suggestionsEl.style.display = 'none';
			suggestionsEl.setAttribute('aria-hidden','true');
		}

		function renderItems(items){
			clearSuggestions();
			if (!items || items.length === 0) return;
			const ul = document.createElement('div');
			ul.className = 'sidebar-suggestions-list';
			items.forEach(it => {
				const el = document.createElement('a');
				el.className = 'sidebar-suggestion-item';
				if (it.username) {
					el.href = '/profile.php?user=' + encodeURIComponent(it.username);
					el.innerHTML = '<strong>' + escapeHtml(it.username) + '</strong>';
				} else if (it.name) {
					el.href = '/pages/team_profile.php?team_id=' + encodeURIComponent(it.id);
					el.innerHTML = '<strong>' + escapeHtml(it.name) + '</strong>' + (it.tag ? ' <span class="small">' + escapeHtml(it.tag) + '</span>' : '');
				}
				ul.appendChild(el);
			});
			suggestionsEl.appendChild(ul);
			suggestionsEl.style.display = 'block';
			suggestionsEl.setAttribute('aria-hidden','false');
		}

	function escapeHtml(s){
		return (s+'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]; });
	}

		inputEl.addEventListener('input', function(){
			const q = inputEl.value.trim();
			if (timer) clearTimeout(timer);
			if (q.length < 2) { clearSuggestions(); return; }
			timer = setTimeout(function(){
				const type = (typeEl && typeEl.value) ? typeEl.value : 'users';
				fetch('/pages/search_suggest.php?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type))
					.then(r => r.json())
					.then(data => renderItems(data))
					.catch(() => clearSuggestions());
			}, 220);
		});

		document.addEventListener('click', function(e){
			if (!suggestionsEl.contains(e.target) && e.target !== inputEl && e.target !== typeEl) {
				clearSuggestions();
			}
		});
	}

	// bind both sidebar and page inputs (if present)
	bindAutocomplete(sidebarInput, sidebarType, sidebarSuggestions);
	bindAutocomplete(pageInput, pageType, pageSuggestions);
})();

