<?php
/**
 * Sistema de Notas Web - Similar ao Simplenote
 * 
 * @version 1.1.7
 */

// Configurações
define('NOTAS_VERSION', '1.1.7');

// Carrega o WordPress
require_once('../wp-load.php');

global $wpdb;

// Nome da tabela
$table_name = $wpdb->prefix . 'notas_notes';

// Cria a tabela se não existir
$charset_collate = $wpdb->get_charset_collate();
$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    title varchar(255) DEFAULT 'Nova Nota',
    content longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY updated_at (updated_at)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// Processa requisições AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'list':
            $notes = $wpdb->get_results(
                "SELECT id, title, LEFT(content, 100) as preview, updated_at 
                FROM $table_name 
                ORDER BY updated_at DESC"
            );
            echo json_encode($notes);
            exit;
            
        case 'get':
            $id = intval($_GET['id']);
            $note = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
            );
            echo json_encode($note);
            exit;
            
        case 'create':
            $wpdb->insert(
                $table_name,
                array(
                    'title' => 'Nova Nota',
                    'content' => ''
                )
            );
            $note = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $wpdb->insert_id)
            );
            echo json_encode($note);
            exit;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id']);
            $title = sanitize_text_field($data['title']);
            $content = wp_kses_post($data['content']);
            
            $wpdb->update(
                $table_name,
                array(
                    'title' => $title,
                    'content' => $content
                ),
                array('id' => $id)
            );
            
            $note = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
            );
            echo json_encode($note);
            exit;
            
        case 'delete':
            $id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $id));
            echo json_encode(array('success' => true));
            exit;
            
        case 'search':
            $query = sanitize_text_field($_GET['q']);
            $notes = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, LEFT(content, 100) as preview, updated_at 
                    FROM $table_name 
                    WHERE title LIKE %s OR content LIKE %s 
                    ORDER BY updated_at DESC",
                    '%' . $wpdb->esc_like($query) . '%',
                    '%' . $wpdb->esc_like($query) . '%'
                )
            );
            echo json_encode($notes);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas - Sistema de Anotações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .container-fluid {
            height: 100vh;
            padding: 0;
        }
        
        .sidebar {
            height: 100vh;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
            padding: 0;
        }
        
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background: #fff;
        }
        
        .search-box {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #0d6efd;
        }
        
        .new-note-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .new-note-btn:hover {
            background: #0b5ed7;
        }
        
        .notes-list {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }
        
        .note-item {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            background: #fff;
            transition: background 0.2s;
        }
        
        .note-item:hover {
            background: #e9ecef;
        }
        
        .note-item.active {
            background: #e7f1ff;
            border-left: 3px solid #0d6efd;
        }
        
        .note-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            color: #212529;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .note-preview {
            font-size: 12px;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .note-date {
            font-size: 11px;
            color: #adb5bd;
            margin-top: 5px;
        }
        
        .editor-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        
        .editor-header {
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .delete-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .delete-btn:hover {
            background: #bb2d3b;
        }
        
        .editor-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            overflow-y: auto;
        }
        
        .note-title-input {
            font-size: 24px;
            font-weight: 600;
            border: none;
            padding: 10px 0;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .note-title-input:focus {
            outline: none;
        }
        
        .note-content-input {
            flex: 1;
            border: none;
            font-size: 16px;
            line-height: 1.6;
            resize: none;
            font-family: inherit;
        }
        
        .note-content-input:focus {
            outline: none;
        }
        
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #adb5bd;
            font-size: 18px;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .sidebar-footer {
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            background: #fff;
        }
        
        .version-info {
            font-size: 11px;
            color: #adb5bd;
            text-align: center;
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1000;
            background: rgba(108, 117, 125, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
        }
        
        .theme-toggle:hover {
            background: rgba(92, 99, 106, 0.8);
            transform: scale(1.05);
        }
        
        /* Dark Mode Styles */
        body.dark-mode {
            background: #1a1a1a;
            color: #e9ecef;
        }
        
        body.dark-mode .sidebar {
            background: #2d3748;
            border-right-color: #4a5568;
        }
        
        body.dark-mode .sidebar-header {
            background: #2d3748;
            border-bottom-color: #4a5568;
        }
        
        body.dark-mode .search-box {
            background: #4a5568;
            border-color: #718096;
            color: #e9ecef;
        }
        
        body.dark-mode .search-box:focus {
            border-color: #63b3ed;
        }
        
        body.dark-mode .new-note-btn {
            background: #3182ce;
        }
        
        body.dark-mode .new-note-btn:hover {
            background: #2c5282;
        }
        
        body.dark-mode .note-item {
            background: #2d3748;
            border-bottom-color: #4a5568;
        }
        
        body.dark-mode .note-item:hover {
            background: #4a5568;
        }
        
        body.dark-mode .note-item.active {
            background: #2b6cb0;
            border-left-color: #63b3ed;
        }
        
        body.dark-mode .note-title {
            color: #e9ecef;
        }
        
        body.dark-mode .note-preview {
            color: #a0aec0;
        }
        
        body.dark-mode .note-date {
            color: #718096;
        }
        
        body.dark-mode .editor-container {
            background: #1a1a1a;
        }
        
        body.dark-mode .editor-header {
            background: #2d3748;
            border-bottom-color: #4a5568;
        }
        
        body.dark-mode .delete-btn {
            background: #e53e3e;
        }
        
        body.dark-mode .delete-btn:hover {
            background: #c53030;
        }
        
        body.dark-mode .back-btn {
            background: #4a5568;
        }
        
        body.dark-mode .back-btn:hover {
            background: #718096;
        }
        
        body.dark-mode .note-title-input {
            background: #2d3748;
            color: #e9ecef;
        }
        
        body.dark-mode .note-content-input {
            background: #2d3748;
            color: #e9ecef;
        }
        
        body.dark-mode .sidebar-footer {
            background: #2d3748;
            border-top-color: #4a5568;
        }
        
        body.dark-mode .version-info {
            color: #718096;
        }
        
        body.dark-mode .empty-state {
            color: #a0aec0;
        }
        
        body.dark-mode .loading {
            color: #a0aec0;
        }
        
        .back-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: none;
        }
        
        .back-btn:hover {
            background: #5c636a;
        }
        
        /* Mobile Styles */
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                width: 100%;
                z-index: 10;
                transition: transform 0.3s ease;
            }
            
            .sidebar.hidden {
                transform: translateX(-100%);
            }
            
            .editor-container {
                position: fixed;
                width: 100%;
                z-index: 5;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            
            .editor-container.show {
                transform: translateX(0);
            }
            
            .back-btn {
                display: inline-block;
            }
            
            .editor-header {
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Alternar tema">
        🌙
    </button>
    <div class="container-fluid">
        <div class="row h-100">
            <!-- Sidebar -->
            <div class="col-md-4 col-lg-3 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <input type="text" class="search-box" id="searchInput" placeholder="Pesquisar notas...">
                    <button class="new-note-btn" id="newNoteBtn">+ Nova Nota</button>
                </div>
                <div class="notes-list" id="notesList">
                    <div class="loading">Carregando...</div>
                </div>
                <div class="sidebar-footer">
                    <div class="version-info">v<?php echo NOTAS_VERSION; ?></div>
                </div>
            </div>
            
            <!-- Editor -->
            <div class="col-md-8 col-lg-9 p-0">
                <div class="editor-container" id="editorContainer">
                    <div class="empty-state">
                        Selecione uma nota ou crie uma nova
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
    <script>
        let notes = [];
        let currentNote = null;
        let saveTimeout = null;

        // Carrega todas as notas
        async function loadNotes() {
            try {
                const response = await fetch('?action=list');
                notes = await response.json();
                renderNotesList();
                
                // Seleciona automaticamente a primeira nota (mais recente)
                if (notes.length > 0 && !currentNote) {
                    await selectNote(notes[0].id);
                }
            } catch (error) {
                console.error('Erro ao carregar notas:', error);
            }
        }

        // Renderiza a lista de notas
        function renderNotesList() {
            const notesList = document.getElementById('notesList');
            
            if (notes.length === 0) {
                notesList.innerHTML = '<div class="loading">Nenhuma nota encontrada</div>';
                return;
            }
            
            notesList.innerHTML = notes.map(note => `
                <div class="note-item ${currentNote && currentNote.id === note.id ? 'active' : ''}" 
                     data-id="${note.id}" 
                     onclick="selectNote(${note.id})">
                    <div class="note-title">${escapeHtml(note.title || 'Nova Nota')}</div>
                    <div class="note-preview">${escapeHtml(stripHtml(note.preview || ''))}</div>
                    <div class="note-date">${formatDate(note.updated_at)}</div>
                </div>
            `).join('');
        }

        // Seleciona uma nota
        async function selectNote(id) {
            try {
                const response = await fetch(`?action=get&id=${id}`);
                currentNote = await response.json();
                renderEditor();
                renderNotesList();
                showEditor();
                return currentNote;
            } catch (error) {
                console.error('Erro ao carregar nota:', error);
                return null;
            }
        }
        
        // Mostra o editor (mobile)
        function showEditor() {
            if (window.innerWidth <= 767) {
                document.getElementById('sidebar').classList.add('hidden');
                document.getElementById('editorContainer').classList.add('show');
            }
        }
        
        // Volta para a lista (mobile)
        function backToList() {
            document.getElementById('sidebar').classList.remove('hidden');
            document.getElementById('editorContainer').classList.remove('show');
        }

        // Renderiza o editor
        function renderEditor() {
            const editorContainer = document.getElementById('editorContainer');
            
            if (!currentNote) {
                editorContainer.innerHTML = '<div class="empty-state">Selecione uma nota ou crie uma nova</div>';
                return;
            }
            
            editorContainer.innerHTML = `
                <div class="editor-header">
                    <button class="back-btn" onclick="backToList()">← Voltar</button>
                    <button class="delete-btn" onclick="deleteNote()">Deletar</button>
                </div>
                <div class="editor-content">
                    <input type="text" 
                           class="note-title-input" 
                           id="noteTitleInput" 
                           value="${currentNote.title === 'Nova Nota' ? '' : escapeHtml(currentNote.title || '')}"
                           placeholder="Nova Nota">
                    <textarea class="note-content-input" 
                              id="noteContentInput" 
                              placeholder="Comece a escrever...">${escapeHtml(currentNote.content || '')}</textarea>
                </div>
            `;
            
            // Adiciona listeners para auto-save
            const titleInput = document.getElementById('noteTitleInput');
            const contentInput = document.getElementById('noteContentInput');
            
            titleInput.addEventListener('input', handleNoteChange);
            contentInput.addEventListener('input', handleNoteChange);
        }

        // Manipula mudanças na nota
        function handleNoteChange() {
            const title = document.getElementById('noteTitleInput').value;
            const content = document.getElementById('noteContentInput').value;
            
            // Se o título estiver vazio, usa as primeiras palavras do conteúdo ou "Nova Nota"
            if (title.trim() === '') {
                const firstLine = content.trim().split('\n')[0];
                const words = firstLine.split(' ').slice(0, 5).join(' ');
                currentNote.title = words || 'Nova Nota';
            } else {
                currentNote.title = title;
            }
            
            currentNote.content = content;
            
            // Debounce para salvar
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => saveNote(), 500);
        }

        // Salva a nota
        async function saveNote() {
            if (!currentNote) return;
            
            try {
                const response = await fetch('?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: currentNote.id,
                        title: currentNote.title,
                        content: currentNote.content
                    })
                });
                
                const updatedNote = await response.json();
                
                // Atualiza a nota na lista
                const index = notes.findIndex(n => n.id === updatedNote.id);
                if (index !== -1) {
                    notes[index] = {
                        ...notes[index],
                        title: updatedNote.title,
                        preview: updatedNote.content,
                        updated_at: updatedNote.updated_at
                    };
                    // Reordena por data de atualização
                    notes.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
                    renderNotesList();
                }
            } catch (error) {
                console.error('Erro ao salvar nota:', error);
            }
        }

        // Cria uma nova nota
        async function createNote() {
            try {
                const response = await fetch('?action=create');
                const newNote = await response.json();
                
                notes.unshift({
                    id: newNote.id,
                    title: newNote.title,
                    preview: '',
                    updated_at: newNote.updated_at
                });
                
                currentNote = newNote;
                renderNotesList();
                renderEditor();
                showEditor();
                
                // Foca no título
                setTimeout(() => {
                    document.getElementById('noteTitleInput').focus();
                }, 100);
            } catch (error) {
                console.error('Erro ao criar nota:', error);
            }
        }

        // Deleta a nota atual
        async function deleteNote() {
            if (!currentNote) return;
            
            if (!confirm('Tem certeza que deseja deletar esta nota?')) return;
            
            try {
                await fetch(`?action=delete&id=${currentNote.id}`);
                
                notes = notes.filter(n => n.id !== currentNote.id);
                currentNote = null;
                
                renderNotesList();
                renderEditor();
                backToList();
            } catch (error) {
                console.error('Erro ao deletar nota:', error);
            }
        }

        // Pesquisa notas
        let searchTimeout = null;
        async function searchNotes(query) {
            clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(async () => {
                if (query.trim() === '') {
                    loadNotes();
                    return;
                }
                
                try {
                    const response = await fetch(`?action=search&q=${encodeURIComponent(query)}`);
                    notes = await response.json();
                    renderNotesList();
                } catch (error) {
                    console.error('Erro ao pesquisar:', error);
                }
            }, 300);
        }

        // Utilitários
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function stripHtml(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            
            // Se a diferença for negativa, a data é futura (erro de sincronização)
            if (diff < 0) return 'Agora';
            
            const minutes = Math.floor(diff / (1000 * 60));
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (minutes < 1) return 'Agora';
            if (minutes < 60) return minutes === 1 ? '1 min atrás' : `${minutes} min atrás`;
            if (hours < 24) return hours === 1 ? '1 hora atrás' : `${hours} horas atrás`;
            if (days === 1) return 'Ontem';
            if (days < 7) return `${days} dias atrás`;
            
            return date.toLocaleDateString('pt-BR');
        }

        // Event Listeners
        document.getElementById('newNoteBtn').addEventListener('click', createNote);
        document.getElementById('searchInput').addEventListener('input', (e) => searchNotes(e.target.value));
        
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        // Carrega tema salvo (padrão: dark mode)
        const savedTheme = localStorage.getItem('notas-theme');
        if (savedTheme !== 'light') {
            body.classList.add('dark-mode');
            themeToggle.textContent = '☀️';
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            themeToggle.textContent = isDark ? '☀️' : '🌙';
            localStorage.setItem('notas-theme', isDark ? 'dark' : 'light');
        });

        // Carrega as notas ao iniciar
        loadNotes();
    </script>
</body>
</html>
