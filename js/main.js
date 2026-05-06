/* Landing page interactions are handled through normal navigation. */

// Sidebar search autocomplete
(function(){
	const input = document.getElementById('sidebar-search-input');
	const typeSelect = document.getElementById('sidebar-search-type');
	const suggestions = document.getElementById('sidebar-search-suggestions');
	if (!input || !suggestions) return;

	let timer = 0;

	function clearSuggestions(){
		suggestions.innerHTML = '';
		suggestions.style.display = 'none';
		suggestions.setAttribute('aria-hidden','true');
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
		suggestions.appendChild(ul);
		suggestions.style.display = 'block';
		suggestions.setAttribute('aria-hidden','false');
	}

	function escapeHtml(s){
		return (s+'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]; });
	}

	input.addEventListener('input', function(){
		const q = input.value.trim();
		if (timer) clearTimeout(timer);
		if (q.length < 2) { clearSuggestions(); return; }
		timer = setTimeout(function(){
			const type = (typeSelect && typeSelect.value) ? typeSelect.value : 'users';
			fetch('/pages/search_suggest.php?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type))
				.then(r => r.json())
				.then(data => renderItems(data))
				.catch(() => clearSuggestions());
		}, 220);
	});

	document.addEventListener('click', function(e){
		if (!suggestions.contains(e.target) && e.target !== input && e.target !== typeSelect) {
			clearSuggestions();
		}
	});
})();

