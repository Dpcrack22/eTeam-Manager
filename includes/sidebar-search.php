<?php
// Sidebar search module (separate include)
?>
<div class="card sidebar-panel" id="sidebar-search-panel">
    <div class="small">Buscar</div>
    <div style="margin-top:8px;">
        <div style="display:flex; gap:8px; align-items:center;">
            <input id="sidebar-search-input" name="q" type="search" placeholder="Buscar usuarios o equipos" aria-label="Buscar" style="flex:1; padding:8px; border-radius:6px; border:1px solid rgba(255,255,255,0.04); background:transparent; color:var(--text-main);" autocomplete="off" />
            <select id="sidebar-search-type" name="type" aria-label="Tipo" style="padding:8px; border-radius:6px; background:transparent; border:1px solid rgba(255,255,255,0.04); color:var(--text-main);">
                <option value="users">Usuarios</option>
                <option value="teams">Equipos</option>
            </select>
        </div>
        <div id="sidebar-search-suggestions" class="sidebar-search-suggestions" aria-hidden="true"></div>
    </div>
</div>
