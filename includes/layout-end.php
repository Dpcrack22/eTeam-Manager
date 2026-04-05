                </div>
            </section>
        </main>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>

    <div class="app-modal" data-team-switcher-modal hidden>
        <div class="app-modal-backdrop" data-modal-close></div>
        <div class="app-modal-panel" role="dialog" aria-modal="true" aria-labelledby="team-switcher-title">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Cambiar equipo</div>
                    <h3 class="h3" id="team-switcher-title">Selecciona un roster</h3>
                </div>
                <button class="btn btn-secondary" type="button" data-modal-close>Salir</button>
            </div>

            <p class="small">El cambio se aplica en toda la app y actualiza el contexto activo sin salir de esta pantalla.</p>

            <?php $sidebarTeams = $appSidebarTeams ?? []; ?>
            <?php if (!empty($sidebarTeams)): ?>
                <div class="team-switcher-list">
                    <?php foreach ($sidebarTeams as $team): ?>
                        <form class="team-switcher-item" method="post" action="app.php?view=teams">
                            <input type="hidden" name="action" value="activate_team" />
                            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($appCurrentRequestUri ?? 'app.php?view=teams', ENT_QUOTES, 'UTF-8'); ?>" />
                            <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                            <button class="team-switcher-button<?php echo !empty($appActiveTeamId) && (int) $appActiveTeamId === (int) $team['id'] ? ' is-active' : ''; ?>" type="submit">
                                <span>
                                    <strong><?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?php echo htmlspecialchars($team['game_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                                <span class="badge badge-info"><?php echo htmlspecialchars($team['tag'] ?: '--', ENT_QUOTES, 'UTF-8'); ?></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-empty-state">No hay equipos disponibles para cambiar.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="app-modal" data-delete-confirm-modal hidden>
        <div class="app-modal-backdrop" data-modal-close></div>
        <div class="app-modal-panel app-modal-panel--compact" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-title">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Confirmar acción</div>
                    <h3 class="h3" id="delete-confirm-title">Eliminar scrim</h3>
                </div>
            </div>

            <p class="small">Esta acción no se puede deshacer. El scrim se eliminará de forma permanente.</p>

            <div class="scrim-note-box" data-delete-confirm-message>Vas a borrar el scrim seleccionado.</div>

            <div class="scrim-form-actions">
                <button class="btn btn-secondary" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="button" data-delete-confirm-accept>Eliminar</button>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ($pageScripts as $scriptPath): ?>
        <script src="<?php echo htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>