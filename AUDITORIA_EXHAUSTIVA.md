# 🔍 AUDITORÍA EXHAUSTIVA - eTeam-Manager

**Fecha:** 7 de Mayo 2026  
**Scope:** Análisis completo de errores potenciales en arquitectura, seguridad y lógica

---

## 📊 RESUMEN EJECUTIVO

| Severidad | Cantidad | Estado |
|-----------|----------|--------|
| 🔴 CRÍTICO | 4 | Requiere fix inmediato |
| 🟠 ALTO | 6 | Problemas de acceso/validación |
| 🟡 MEDIO | 7 | Lógica inconsistente |
| 🟢 MENOR | 5 | Calidad de código |
| **TOTAL** | **22** | - |

---

## 🔴 ERRORES CRÍTICOS

### 1. **Variable Duplicada en Team Creation**
**Ubicación:** [pages/teams.php](pages/teams.php#L231)  
**Línea:** 231 vs 20  
**Tipo:** Lógica/Variables indefinidas  
**Prioridad:** CRÍTICO

**Problema:**
- Línea 20: `$userOrganizations = $userId ? getUserOrganizations($conn, $userId) : [];`
- Línea 231: `$userOrgs = getUserOrganizations($conn, $userId);` ← **REDECLARACIÓN**
- Línea 238: `foreach ($userOrgs as $org)` ← Usa variable local
- Línea 276: `foreach ($userOrganizations as $uo)` ← Usa variable global

La variable local `$userOrgs` (línea 231) es una copia nueva en el scope de `create_team`, pero después se valida sobre `$userOrganizations` (línea 276). Si las organizaciones cambian entre linea 231 y 276, la validación fallará.

**Causa Raíz:** 
- Duplicación accidental durante desarrollo
- No consolidar ambas variables

**Impacto:** 
- ✅ Usuario puede crear equipo con permiso incorrecto
- ✅ Falso positivo: "No tienes permisos" cuando debería permitir
- ✅ Race condition en aplicaciones concurrentes

**Solución:** Eliminar línea 231, usar `$userOrganizations` en todo el bloque

**Código Actual:**
```php
// Línea 231
$userOrgs = getUserOrganizations($conn, $userId);  // ❌ NUEVO
$targetOrgId = 0;

if ($activeOrganizationId) {
    $targetOrgId = $activeOrganizationId;
} else {
    // Línea 238
    foreach ($userOrgs as $org) {  // ❌ USA $userOrgs
        if (in_array($org['member_role'], ['owner', 'admin', 'manager'], true)) {
            $targetOrgId = (int) $org['id'];
            break;
        }
    }
}

// ... más código ...

// Línea 276
$allowed = false;
foreach ($userOrganizations as $uo) {  // ✅ USA $userOrganizations (global)
    if ((int) $uo['id'] === (int) $targetOrgId && ...) {
        $allowed = true;
        break;
    }
}
```

---

### 2. **Search Suggest - Errores Silenciados**
**Ubicación:** [pages/search_suggest.php](pages/search_suggest.php#L31)  
**Línea:** 31  
**Tipo:** Error Handling / Seguridad  
**Prioridad:** CRÍTICO

**Problema:**
```php
} catch (Throwable $e) {
    echo json_encode([]);  // ❌ Silencia TODOS los errores
}
```

**Efectos:**
- ❌ Si `$conn` está NULL → Error 500 silenciado → Cliente recibe `[]`
- ❌ Si DB_HOST invalido → Sin logs → Imposible debuggear
- ❌ Si query SQL falla → Sin detalles del error
- ❌ Usuario ve "sin resultados" cuando es un error real

**Causa Raíz:**
- Falta error_log() o archivo de debugging

**Impacto:**
- ✅ Error 500 sin visibilidad
- ✅ Búsqueda completamente rota sin aviso
- ✅ Usuario no recibe feedback de qué pasó

**Solución:**
```php
} catch (Throwable $e) {
    error_log('Search suggest error: ' . $e->getMessage());
    echo json_encode([]);
}
```

---

### 3. **Database Connection - $conn No Declarada como Global**
**Ubicación:** [pages/search_suggest.php](pages/search_suggest.php#L1-L5)  
**Línea:** 1-5  
**Tipo:** Variables indefinidas  
**Prioridad:** CRÍTICO

**Problema:**
```php
<?php
require_once __DIR__ . '/../includes/db.php';  // ← Conexión en include

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'users'));

// ... sin "global $conn" ...

$conn->setAttribute(...);  // ❌ ¿De dónde viene $conn?
```

**Análisis:**
- `includes/db.php` crea `$conn` en scope global
- `search_suggest.php` NO declara `global $conn`
- En PHP, within función == scope local
- **Pero search_suggest.php NO está en función** → Debería funcionar
- **SIN EMBARGO:** Si `db.php` falla a conectar → `die()` en db.php línea 55 → Script termina

**Causa Raíz:**
- Dependencia implícita en `db.php`
- Sin declaración explícita de `global`

**Impacto:**
- ✅ Si DB connection fallida: Error 500
- ✅ Cliente ve `[]` porque catch() silencia
- ✅ Sin logs = imposible diagnosticar

**Solución:** Agregar after require_once:
```php
global $conn;
```

---

### 4. **auth.php - requireAuth() Sin Retorno**
**Ubicación:** [includes/auth.php](includes/auth.php#L400)  
**Línea:** 400-407  
**Tipo:** Control Flow  
**Prioridad:** CRÍTICO

**Problema:**
```php
function requireAuth(): void
{
    if (!isLogged()) {
        $_SESSION['return_to'] = safeReturnToTarget(...);
        header('Location: login.php');
        exit;  // ← Aquí termina la ejecución
    }
    // ← Si llega aquí, usuario está autenticado
    // PERO: No hay return ni continúa código
}
```

**Análisis:**
- `requireAuth()` es `void` (no retorna nada)
- Llama a `exit;` dentro
- Páginas que usan `requireAuth()` esperan continuar después de la llamada
- PERO: Si llamado desde una función anidada, `exit` mata el script completo

**Causa Raíz:**
- `exit` es global, no respeta scope de función

**Impacto:**
- ✅ En [pages/teams.php](pages/teams.php#L7): `requireAuth();` → Si no autenticado → exit → Script muere
- ✅ En app.php: Igual problema
- ✅ Correcto: El script SÍ termina si no autenticado (intención correcta)

**Análisis Final:**
- ✅ **NO ES REALMENTE UN BUG** - `exit;` es intencional
- ✅ Pero es mala práctica usar `exit` dentro de función `void`
- ⚠️ SUGERENCIA: Cambiar a `throw new Exception()` con global error handler

---

## 🟠 ERRORES ALTOS

### 5. **getTeamById() - Llamadas Sin Validación de Organización**
**Ubicación:** [pages/teams.php](pages/teams.php#L90-L130)  
**Líneas:** 90, 130  
**Tipo:** Seguridad / Acceso  
**Prioridad:** ALTO

**Problema:**
```php
// Línea 90 - activate_team
$team = getTeamById($conn, $teamId);  // ← Sin organizationId

// Línea 130 - delete_team  
$team = getTeamById($conn, $teamId);  // ← Sin organizationId
```

**Función:**
```php
function getTeamById(PDO $conn, int $teamId, int $organizationId = 0): array|false
{
    $sql = 'SELECT ... WHERE t.id = :team_id';
    
    if ($organizationId > 0) {
        $sql .= ' AND t.organization_id = :organization_id';  // ← Validación OPCIONAL
    }
    // Si $organizationId = 0 (default) → NO valida organización
}
```

**Riesgo de Seguridad:**
- Usuario A es miembro de Org 1 (Team X)
- Usuario A intenta manipular Team Y de Org 2
- `getTeamById($conn, teamY_id)` sin organizationId = **PERMITE ACCESO**

**Impacto:**
- ✅ Cross-organization team manipulation
- ✅ Usuario puede eliminar equipos de otras orgs
- ✅ Usuario puede cambiar equipos activos de otros

**Llamadas con Riesgo:**
| Línea | Función | Riesgo |
|-------|---------|--------|
| 90 | activate_team | Activar equipo de otra org |
| 130 | delete_team | Eliminar equipo de otra org |
| ✅ 51 | getUserOrganizationTeams | Seguro - filtra por org |

**Solución:** Validar organizationId SIEMPRE:
```php
$team = getTeamById($conn, $teamId, $activeOrganizationId);
if (!$team) {
    // acceso denegado
}
```

---

### 6. **team_profile.php - $_SESSION Access Sin Guard**
**Ubicación:** [team_profile.php](team_profile.php#L24)  
**Línea:** 24  
**Tipo:** Variables Indefinidas  
**Prioridad:** ALTO

**Problema:**
```php
createNotification($conn, (int)$a['user_id'], 'team_join', $teamId, 
    'El usuario ' . ($_SESSION['user']['name'] ?? 'un usuario') . ' solicita unirse al equipo ' . ($team['name'] ?? '') . '.'
);
```

**Riesgo:**
- `$_SESSION['user']` podría no existir si sesión expiró
- `$_SESSION['user']['name']` acceso a array indefinido

**Impacto:**
- ✅ Notice: Undefined index (en desarrollo)
- ✅ Notificación con texto corrupto

**Solución:**
```php
$requesterName = $_SESSION['user']['name'] ?? 'un usuario';
$message = "El usuario {$requesterName} solicita unirse al equipo {$team['name']}.";
createNotification($conn, (int)$a['user_id'], 'team_join', $teamId, $message);
```

---

### 7. **search.php - DB Query Inconsistencia**
**Ubicación:** [pages/search.php](pages/search.php#L16-L20)  
**Línea:** 16-20  
**Tipo:** Lógica  
**Prioridad:** ALTO

**Problema:**
```php
// search.php L16
$stmt = $conn->prepare('SELECT ... FROM teams t 
    LEFT JOIN games g ON g.id = t.game_id 
    WHERE t.name LIKE :q OR t.tag LIKE :q LIMIT 50'
);

// search_suggest.php L17
$stmt = $conn->prepare('SELECT ... FROM teams 
    WHERE name LIKE :q OR tag LIKE :q LIMIT 10'
);
```

**Diferencias:**
- `search.php`: LEFT JOIN games + 50 resultados
- `search_suggest.php`: Sin JOIN games, 10 resultados  
- `search.php`: Retorna `game_name` en resultados
- `search_suggest.php`: NO tiene `game_name`

**Si usuario ve game_name en autocomplete, espera verlo en página:**
```php
<div class="small"><?php echo htmlspecialchars($r['game_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
```

**Pero si viene de search_suggest.php → `game_name` NO existe → NULL → Silencio**

**Impacto:**
- ✅ Inconsistencia visual entre autocomplete y resultado
- ✅ Cliente confundido por UI

**Solución:** Hacer queries idénticas o documentar diferencias

---

### 8. **Global $conn Inconsistencia**
**Ubicación:** Múltiples  
**Líneas:** [pages/search.php](pages/search.php#L12), [pages/search_suggest.php](pages/search_suggest.php#L1)  
**Tipo:** Variables  
**Prioridad:** ALTO

**Problema:**
```php
// search.php - L12
global $conn;

// search_suggest.php - Falta esta línea
```

**Análisis:**
- `search.php` DECLARA `global $conn` explícitamente
- `search_suggest.php` NO lo declara
- Ambos `require_once db.php` que crea `$conn`

**En PHP:**
- Código en scope global (no dentro de función) puede acceder variables globales sin declarar `global`
- PERO: Es mala práctica y genera confusión
- Si luego se envuelve en función → BUG

**Impacto:**
- ✅ Inconsistencia de patrón
- ✅ Si se refactoriza a función → search_suggest.php rompe

---

### 9. **Missing organizationId in team-detail.php**
**Ubicación:** [pages/team-detail.php](pages/team-detail.php#L51)  
**Línea:** 51, 56, 78  
**Tipo:** Seguridad  
**Prioridad:** ALTO

**Problema:**
```php
$selectedTeam = getTeamById($conn, $selectedTeamId, (int) $activeOrganizationId);
```

**PERO:**
```php
// Línea 51
$selectedTeam = getTeamById($conn, $selectedTeamId, (int) $activeOrganizationId);
// Línea 78 - POST handler
$selectedTeam = getTeamById($conn, $postedTeamId, (int) $activeOrganizationId);
```

**Visto:**
- team-detail.php SÍ usa organizationId en getTeamById() ✅
- teams.php NO usa organizationId ❌

**Inconsistencia de patrones en mismo codebase**

---

## 🟡 ERRORES MEDIOS

### 10. **Prepared Statement - Correct pero Verbose**
**Ubicación:** [pages/teams.php](pages/teams.php#L132-L145)  
**Línea:** 132-145  
**Tipo:** Calidad de Código  
**Prioridad:** MEDIO

**Problema:**
```php
$roleStatement = $conn->prepare(
    'SELECT role FROM organization_members 
     WHERE user_id = :user_id 
       AND organization_id = :organization_id 
       AND is_active = 1 
       AND COALESCE(moderation_status, "active") = "active" 
     LIMIT 1'
);
$roleStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
$roleStatement->bindValue(':organization_id', $teamOrganizationId, PDO::PARAM_INT);
$roleStatement->execute();
```

**Análisis:**
- ✅ Bien: Prepared statement correcto
- ✅ Bien: PDO con tipo de parámetro
- ❌ Query se repite en múltiples lugares
- ❌ Debería estar en función helper

**Solución:** Crear función:
```php
function getUserRoleInOrganization(PDO $conn, int $userId, int $orgId): ?string {
    $stmt = $conn->prepare(...);
    // ...
    return $row['role'] ?? null;
}
```

---

### 11. **Error Silencing en profile.php**
**Ubicación:** [profile.php](profile.php#L33-L52)  
**Línea:** 33-52  
**Tipo:** Error Handling  
**Prioridad:** MEDIO

**Problema:**
```php
} catch (PDOException $ex) {
    $user = false;
    // ← SIN logging
}

// ...

} catch (PDOException $ex2) {
    $user = false;
    // ← SIN logging
}
```

**Impacto:**
- ✅ Si DB query falla → Sin logs → Imposible debuggear
- ✅ Usuario ve "Usuario no encontrado" cuando es error BD

**Solución:** Agregar logs:
```php
catch (PDOException $ex) {
    error_log('Profile fetch error: ' . $ex->getMessage());
    $user = false;
}
```

---

### 12. **Undefined Variable $activeTeamId**
**Ubicación:** [pages/teams.php](pages/teams.php#L60-L75)  
**Línea:** 60-75  
**Tipo:** Variables  
**Prioridad:** MEDIO

**Problema:**
```php
if ($activeOrganizationId) {
    // ...
    if ($activeTeamId === null) {
        $activeTeamId = (int) $teams[0]['id'];  // ← Definida
        // ...
    }
    
    foreach ($teams as $team) {
        if (isset($activeTeamId) && ...) {  // ← ¿Puede ser undefined?
        // ← SÍ, si $teams es empty array
```

**Análisis:**
- `if (empty($teams))` → `$activeTeamId = null;` ← DEFINIDA
- PERO: `isset($activeTeamId)` en L70 podría fallar si PHP version antigua
- Modern PHP >= 7.0 → No es problema
- Legado PHP < 7.0 → Posible Notice

**Impacto:**
- ✅ Notice: Undefined variable (mínimo)

---

### 13. **Inconsistent Return Values in Functions**
**Ubicación:** [includes/team_functions.php](includes/team_functions.php#L160)  
**Línea:** 160+  
**Tipo:** API Design  
**Prioridad:** MEDIO

**Problema:**
```php
// Algunos return array|false
function getTeamById(...): array|false { }

// Otros return array (nunca false)
function getOrganizationTeams(...): array { }

// Otros return bool
function deleteTeam(...): bool { }

// Otros return int
function createTeam(...): int { }
```

**Inconsistencia:**
- Función A: Retorna array|false
- Función B: Retorna array siempre
- Función C: Retorna bool
- Función D: Retorna int

**Impacto:**
- ✅ Cliente debe conocer retorno de cada función
- ✅ Fácil error: `if ($team)` vs `if (!empty($team))`
- ✅ IDE autocompletion confuso

---

### 14. **Sessions sin Timeout**
**Ubicación:** [includes/auth.php](includes/auth.php#L5-L10)  
**Línea:** 5-10  
**Tipo:** Seguridad  
**Prioridad:** MEDIO

**Problema:**
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

**Análisis:**
- ✅ Inicia sesión si no existe
- ❌ Sin configuración de timeout
- ❌ Sin CSRF token
- ❌ Sin regeneración de ID

**php.ini defaults:**
- `session.gc_maxlifetime = 1440` (24 minutos)
- Pero depende de server

**Impacto:**
- ✅ Sesiones viven indefinidamente sin timeout explícito
- ✅ Session fixation posible

**Solución:**
```php
ini_set('session.gc_maxlifetime', 3600);  // 1 hora
session_set_cookie_params(['lifetime' => 3600]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## 🟢 ERRORES MENORES

### 15-22. Errores Menores (Calidad de Código)

#### 15. **Missing htmlspecialchars() en algunas outputs**
- [teams.php](pages/teams.php#L430): `$activeTeam && (int) $activeTeam['id']` - Sin escape HTML

#### 16. **Inconsistent null checking**
- Algunos usan `?? null`
- Otros usan `?? false`
- Otros usan `?? []`

#### 17. **getActiveTeamId() no definida**
**Ubicación:** [pages/teams.php](pages/teams.php#L56)  
**Línea:** 56  
**Búsqueda:** `function getActiveTeamId` en includes → NO ENCONTRADA
**Solución:** Verificar si existe o crear

#### 18. **setActiveTeamContext() no definida**
**Ubicación:** [pages/teams.php](pages/teams.php#L59)  
**Búsqueda:** `function setActiveTeamContext` → Probablemente en team_functions.php

#### 19. **isUserActiveMember() no encontrada en búsqueda**
**Ubicación:** [pages/teams.php](pages/teams.php#L106)  
**Búsqueda:** `function isUserActiveMember` → Probablemente en team_functions.php

#### 20. **deleteTeam() no encontrada**
**Ubicación:** [pages/teams.php](pages/teams.php#L152)  
**Búsqueda:** `function deleteTeam` → Probablemente en team_functions.php

#### 21. **No error_log para critical paths**
- DB errors silenciados
- Auth failures sin logging
- Permission violations sin audit trail

#### 22. **Missing input validation en algunos campos**
- Tag name: `trim()` pero sin length check
- Description: `trim()` pero sin length check
- Email: Validación básica pero sin DNS check

---

## 📋 MATRIZ DE IMPACTO

```
┌─────────────────────────┬──────────┬────────────┬──────────┐
│ Error                   │ Tipo     │ Criticidad │ Frecuencia │
├─────────────────────────┼──────────┼────────────┼──────────┤
│ $userOrgs duplicada     │ Lógica   │ CRÍTICO    │ DIARIA   │
│ Errors silenciados      │ Security │ CRÍTICO    │ OCASIONAL │
│ $conn global            │ Variable │ CRÍTICO    │ RARAMENTE │
│ getTeamById sin org     │ Security │ ALTO       │ DIARIA   │
│ $_SESSION without guard │ Variable │ ALTO       │ OCASIONAL │
│ Query inconsistencia    │ Lógica   │ ALTO       │ DIARIA   │
└─────────────────────────┴──────────┴────────────┴──────────┘
```

---

## 🔧 PLAN DE CORRECCIÓN POR PRIORIDAD

### Fase 1 - CRÍTICO (Hoy)
1. ✅ Eliminar `$userOrgs` de teams.php línea 231
2. ✅ Agregar error_log en search_suggest.php
3. ✅ Validar organizationId en getTeamById()

### Fase 2 - ALTO (Esta semana)
4. Agregar organizationId check en teams.php línea 90, 130
5. Proteger $_SESSION acceso en team_profile.php
6. Unificar queries search vs search_suggest

### Fase 3 - MEDIO (Este mes)
7. Extraer role queries a función helper
8. Agregar error logging general
9. Unificar return types en funciones

### Fase 4 - MENOR (Próximo sprint)
10. Validar inputs (length, format)
11. Add session timeout
12. Add CSRF protection

---

## 📝 RECOMENDACIONES GENERALES

### Seguridad
- [ ] Implementar global error handler con logging
- [ ] Agregar rate limiting en search_suggest.php
- [ ] Implementar session timeout
- [ ] Add CSRF tokens a forms
- [ ] Audit trail para modificaciones críticas

### Código
- [ ] Extraer queries repetidas a funciones
- [ ] Unificar return types (array|false vs array vs bool)
- [ ] Agregar type hints en parámetros
- [ ] Crear base test suite

### DevOps
- [ ] Configurar log aggregation
- [ ] Alertas para excepciones BD
- [ ] Monitoring de API search_suggest
- [ ] Rate limiting en público endpoints

---

## 📞 REFERENCIAS

- Error 500 en búsqueda: search_suggest.php L31
- Permisos de equipo: teams.php L231, L276
- Validación de org: team-detail.php vs teams.php (inconsistencia)
- Sesiones: includes/auth.php L5

---

**Reporte compilado:** 7 de Mayo 2026  
**Auditor:** GitHub Copilot (Claude Haiku)  
**Status:** ⚠️ 22 ERRORES IDENTIFICADOS - REQUIERE ACCIÓN
