<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeerFlow PHP - Agent Auto-Codeur</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #475569;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 20px 0;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .logo span {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .status-bar {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .input-section {
            margin-bottom: 20px;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            color: var(--text-primary);
            font-size: 14px;
            resize: vertical;
            font-family: inherit;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .input-options {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-secondary);
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-primary:disabled {
            background: var(--border);
            cursor: not-allowed;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .output-section {
            margin-top: 20px;
        }

        .output-container {
            background: var(--bg-dark);
            border-radius: 8px;
            padding: 15px;
            min-height: 300px;
            max-height: 600px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
        }

        .event-log {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .event-item {
            padding: 10px;
            border-radius: 6px;
            background: var(--bg-input);
            border-left: 3px solid var(--border);
        }

        .event-item.task_start { border-left-color: var(--primary); }
        .event-item.planning_start { border-left-color: var(--warning); }
        .event-item.planning_complete { border-left-color: var(--success); }
        .event-item.step_start { border-left-color: var(--primary); }
        .event-item.step_complete { border-left-color: var(--success); }
        .event-item.step_error { border-left-color: var(--danger); }
        .event-item.task_complete { border-left-color: var(--success); }
        .event-item.error { border-left-color: var(--danger); background: rgba(239, 68, 68, 0.1); }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .event-type {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .event-time {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .event-content {
            color: var(--text-primary);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-input);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
            width: 0%;
        }

        .sidebar-section {
            margin-bottom: 20px;
        }

        .thread-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
        }

        .thread-item {
            padding: 12px;
            background: var(--bg-input);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .thread-item:hover {
            background: var(--border);
        }

        .thread-item.active {
            border: 1px solid var(--primary);
        }

        .thread-title {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .thread-status {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .thread-status.running { background: rgba(99, 102, 241, 0.2); color: var(--primary); }
        .thread-status.completed { background: rgba(34, 197, 94, 0.2); color: var(--success); }
        .thread-status.failed { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .thread-status.pending { background: rgba(245, 158, 11, 0.2); color: var(--warning); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .stat-card {
            background: var(--bg-input);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .files-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--bg-input);
            border-radius: 6px;
            font-size: 13px;
        }

        .file-icon {
            font-size: 18px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
        }

        .code-block {
            background: var(--bg-dark);
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            margin: 10px 0;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-dark);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">🦌</div>
                    <div>
                        <h1>DeerFlow PHP</h1>
                        <span>Agent Auto-Codeur Autonome</span>
                    </div>
                </div>
                <div class="status-bar">
                    <div class="status-item">
                        <div class="status-dot" id="apiStatus"></div>
                        <span id="apiStatusText">Vérification...</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="main-grid">
            <div class="main-content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Nouvelle Tâche</h2>
                    </div>
                    <div class="card-body">
                        <div class="input-section">
                            <textarea 
                                id="taskInput" 
                                placeholder="Décrivez votre tâche complexe ici...&#10;&#10;Exemples:&#10;- Crée une API REST complète avec authentification JWT&#10;- Analyse le marché des solutions SaaS et génère un rapport&#10;- Développe un système de gestion de contenu avec SQLite"
                            ></textarea>
                            <div class="input-options">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="usePlanning" checked>
                                    Utiliser la planification automatique
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="streamEvents" checked>
                                    Streaming en temps réel
                                </label>
                            </div>
                            <div style="margin-top: 20px;">
                                <button class="btn btn-primary" id="executeBtn" onclick="executeTask()">
                                    🚀 Exécuter la tâche
                                </button>
                                <button class="btn btn-secondary" onclick="clearOutput()" style="margin-left: 10px;">
                                    🗑️ Effacer
                                </button>
                            </div>
                        </div>

                        <div class="output-section">
                            <div class="card-header">
                                <h2 class="card-title">Résultats & Logs</h2>
                                <div id="progressContainer" style="display: none;">
                                    <span id="progressText" style="font-size: 12px; color: var(--text-secondary);"></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progressFill"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="output-container" id="outputContainer">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📝</div>
                                    <p>Les résultats et logs apparaîtront ici</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <div class="card sidebar-section">
                    <div class="card-header">
                        <h2 class="card-title">Statistiques</h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid" id="statsGrid">
                            <div class="stat-card">
                                <div class="stat-value" id="statTotal">0</div>
                                <div class="stat-label">Tâches totales</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="statRunning">0</div>
                                <div class="stat-label">En cours</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="statCompleted">0</div>
                                <div class="stat-label">Terminées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="statFailed">0</div>
                                <div class="stat-label">Échouées</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card sidebar-section">
                    <div class="card-header">
                        <h2 class="card-title">Tâches Récentes</h2>
                        <button class="btn btn-secondary" onclick="loadThreads()" style="padding: 6px 12px; font-size: 12px;">
                            🔄 Actualiser
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="thread-list" id="threadList">
                            <div class="empty-state">
                                <p>Aucune tâche récente</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Fichiers Générés</h2>
                    </div>
                    <div class="card-body">
                        <div class="files-list" id="filesList">
                            <div class="empty-state">
                                <p>Aucun fichier généré</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour voir les détails -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Détails de la tâche</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <script>
        const API_BASE = 'gateway.php';
        let currentTaskId = null;
        let eventSource = null;

        // Vérifier l'état de l'API au chargement
        document.addEventListener('DOMContentLoaded', () => {
            checkApiHealth();
            loadThreads();
            loadStats();
        });

        async function checkApiHealth() {
            try {
                const response = await fetch(`${API_BASE}?route=/api/health`);
                const health = await response.json();
                
                const statusDot = document.getElementById('apiStatus');
                const statusText = document.getElementById('apiStatusText');
                
                if (health.status === 'healthy') {
                    statusDot.style.background = '#10b981';
                    statusText.textContent = 'Opérationnel';
                } else {
                    statusDot.style.background = '#ef4444';
                    statusText.textContent = 'Problème détecté';
                }
            } catch (error) {
                document.getElementById('apiStatus').style.background = '#ef4444';
                document.getElementById('apiStatusText').textContent = 'Hors ligne';
            }
        }

        async function executeTask() {
            const description = document.getElementById('taskInput').value.trim();
            if (!description) {
                alert('Veuillez décrire votre tâche');
                return;
            }

            const usePlanning = document.getElementById('usePlanning').checked;
            const streamEvents = document.getElementById('streamEvents').checked;

            const executeBtn = document.getElementById('executeBtn');
            executeBtn.disabled = true;
            executeBtn.innerHTML = '<span class="loading-spinner"></span> Exécution en cours...';

            clearOutput();

            try {
                const formData = new FormData();
                formData.append('description', description);
                formData.append('use_planning', usePlanning ? '1' : '0');

                const response = await fetch(`${API_BASE}?route=/api/task`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    currentTaskId = result.task_id;
                    addEvent('task_start', {
                        task_id: result.task_id,
                        title: description.substring(0, 100)
                    });

                    if (result.plan) {
                        addEvent('planning_complete', {
                            summary: result.plan.summary,
                            steps_count: result.plan.estimated_steps
                        });
                    }

                    if (result.synthesis) {
                        addEvent('task_complete', {
                            synthesis: result.synthesis.content
                        });
                        displayFiles(result.files || []);
                    }

                    loadThreads();
                    loadStats();
                } else {
                    addEvent('error', { message: result.error || 'Erreur inconnue' });
                }
            } catch (error) {
                addEvent('error', { message: error.message });
            } finally {
                executeBtn.disabled = false;
                executeBtn.innerHTML = '🚀 Exécuter la tâche';
            }
        }

        function addEvent(type, data) {
            const container = document.getElementById('outputContainer');
            const emptyState = container.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            const eventDiv = document.createElement('div');
            eventDiv.className = `event-item ${type}`;

            const time = new Date().toLocaleTimeString('fr-FR');
            
            let content = '';
            if (type === 'planning_complete') {
                content = `✅ Planification terminée\n📊 ${data.steps_count} étapes prévues\n\n${data.summary || ''}`;
            } else if (type === 'task_complete') {
                content = `✅ Tâche terminée!\n\n${data.synthesis || 'Voir les résultats ci-dessus'}`;
            } else if (type === 'error') {
                content = `❌ Erreur: ${data.message}`;
            } else {
                content = JSON.stringify(data, null, 2);
            }

            eventDiv.innerHTML = `
                <div class="event-header">
                    <span class="event-type">${type.replace(/_/g, ' ')}</span>
                    <span class="event-time">${time}</span>
                </div>
                <div class="event-content">${escapeHtml(content)}</div>
            `;

            const eventLog = container.querySelector('.event-log') || createEventLog(container);
            eventLog.appendChild(eventDiv);
            container.scrollTop = container.scrollHeight;
        }

        function createEventLog(container) {
            const log = document.createElement('div');
            log.className = 'event-log';
            container.innerHTML = '';
            container.appendChild(log);
            return log;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function clearOutput() {
            const container = document.getElementById('outputContainer');
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <p>Les résultats et logs apparaîtront ici</p>
                </div>
            `;
            document.getElementById('progressContainer').style.display = 'none';
        }

        function updateProgress(progress, completed, total) {
            const container = document.getElementById('progressContainer');
            const fill = document.getElementById('progressFill');
            const text = document.getElementById('progressText');

            container.style.display = 'block';
            fill.style.width = `${progress}%`;
            text.textContent = `${completed}/${total} étapes complétées (${progress}%)`;
        }

        async function loadThreads() {
            try {
                const response = await fetch(`${API_BASE}?route=/api/threads&limit=10`);
                const data = await response.json();

                const list = document.getElementById('threadList');
                
                if (!data.threads || data.threads.length === 0) {
                    list.innerHTML = '<div class="empty-state"><p>Aucune tâche récente</p></div>';
                    return;
                }

                list.innerHTML = data.threads.map(thread => `
                    <div class="thread-item" onclick="viewThread('${thread.id}')">
                        <div class="thread-title">${escapeHtml(thread.title || 'Sans titre')}</div>
                        <div class="thread-meta">
                            <span>${new Date(thread.created_at).toLocaleDateString('fr-FR')}</span>
                            <span class="thread-status ${thread.status}">${thread.status}</span>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Erreur chargement threads:', error);
            }
        }

        async function loadStats() {
            try {
                const response = await fetch(`${API_BASE}?route=/api/stats`);
                const stats = await response.json();

                if (stats.threads) {
                    document.getElementById('statTotal').textContent = stats.threads.total || 0;
                    document.getElementById('statRunning').textContent = stats.threads.running || 0;
                    document.getElementById('statCompleted').textContent = stats.threads.completed || 0;
                    document.getElementById('statFailed').textContent = stats.threads.failed || 0;
                }
            } catch (error) {
                console.error('Erreur chargement stats:', error);
            }
        }

        async function viewThread(threadId) {
            try {
                const response = await fetch(`${API_BASE}?route=/api/threads/${threadId}`);
                const data = await response.json();

                document.getElementById('modalTitle').textContent = data.thread?.title || 'Détails';
                
                let content = `<strong>ID:</strong> ${threadId}<br>`;
                content += `<strong>Statut:</strong> ${data.thread?.status || 'inconnu'}<br>`;
                content += `<strong>Créé:</strong> ${data.thread?.created_at || 'N/A'}<br><br>`;

                if (data.plan) {
                    content += `<h3>Plan</h3><p>${escapeHtml(data.plan.summary || '')}</p>`;
                    content += `<p>Étapes: ${data.plan.estimated_steps || 0}</p>`;
                }

                if (data.steps && data.steps.length > 0) {
                    content += `<h3>Étapes</h3>`;
                    data.steps.forEach(step => {
                        content += `<div style="padding: 8px; margin: 5px 0; background: var(--bg-input); border-radius: 4px;">`;
                        content += `<strong>${escapeHtml(step.title)}</strong><br>`;
                        content += `<small>Statut: ${step.status} | Skill: ${step.skill_required}</small>`;
                        content += `</div>`;
                    });
                }

                document.getElementById('modalBody').innerHTML = content;
                document.getElementById('detailModal').classList.add('active');
            } catch (error) {
                alert('Erreur lors du chargement des détails');
            }
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
        }

        function displayFiles(files) {
            const list = document.getElementById('filesList');
            
            if (!files || files.length === 0) {
                list.innerHTML = '<div class="empty-state"><p>Aucun fichier généré</p></div>';
                return;
            }

            list.innerHTML = files.map(file => `
                <div class="file-item">
                    <span class="file-icon">📄</span>
                    <span>${escapeHtml(file)}</span>
                </div>
            `).join('');
        }

        // Fermer le modal en cliquant dehors
        document.getElementById('detailModal').addEventListener('click', (e) => {
            if (e.target.id === 'detailModal') {
                closeModal();
            }
        });

        // Rafraîchissement automatique des stats
        setInterval(loadStats, 30000);
        setInterval(loadThreads, 30000);
    </script>
</body>
</html>
