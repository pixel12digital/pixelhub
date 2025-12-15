<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Pixel Hub' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #023A8D;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-user span {
            font-size: 14px;
        }
        .header-user a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 5px 10px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            transition: background 0.3s;
        }
        .header-user a:hover {
            background: rgba(255,255,255,0.1);
        }
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.05);
            padding: 20px 0;
        }
        .sidebar a {
            display: block;
            padding: 12px 30px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
        }
        .sidebar a:hover {
            background: #f0f0f0;
        }
        .sidebar a.active {
            background: #023A8D;
            color: white;
        }
        .sidebar .section-header {
            font-weight: 600;
            color: #023A8D;
            margin-top: 10px;
        }
        .sidebar a.sub-item {
            padding-left: 50px;
            font-size: 13px;
            color: #666;
        }
        .sidebar a.sub-item.active {
            background: #023A8D;
            color: white;
        }
        /* Links de topo sem subitens (Dashboard, Clientes) */
        .sidebar-top-link {
            display: block;
            padding: 12px 30px;
            color: #023A8D;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
        }
        .sidebar-top-link:hover {
            background: #f0f0f0;
        }
        .sidebar-top-link.active {
            background: #023A8D;
            color: white;
        }
        /* Estilos para módulos do menu (accordion) */
        .sidebar-module {
            margin-bottom: 2px;
        }
        .sidebar-module-header {
            display: block;
            padding: 12px 30px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: #023A8D;
            cursor: pointer;
            transition: background 0.3s;
            user-select: none;
        }
        .sidebar-module-header:hover {
            background: #f0f0f0;
        }
        .sidebar-module-header.active {
            background: #023A8D;
            color: white;
        }
        .sidebar-module-header.has-children::after {
            content: '▼';
            float: right;
            font-size: 10px;
            transition: transform 0.3s;
        }
        .sidebar-module-header.is-open::after {
            transform: rotate(180deg);
        }
        .sidebar-module-content {
            display: none;
            overflow: hidden;
        }
        .sidebar-module-content.is-open {
            display: block;
        }
        .sidebar-module-content .sub-item {
            padding-left: 50px;
            font-size: 13px;
            color: #666;
        }
        /* Títulos internos (não clicáveis) */
        .sidebar-internal-title {
            padding: 8px 30px 4px 50px;
            font-size: 11px;
            color: #999;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }
        .content {
            flex: 1;
            padding: 30px;
        }
        .content-header {
            margin-bottom: 30px;
        }
        .content-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .stat-card .value {
            font-size: 32px;
            color: #023A8D;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Pixel Hub</h1>
        <div class="header-user">
            <button type="button"
                    onclick="startGlobalScreenRecording()"
                    style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; margin-right: 10px; transition: background 0.3s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.2)'"
                    title="Gravar tela (contexto inteligente)">
                🎥 Gravar tela
            </button>
            <span><?= htmlspecialchars($user['name'] ?? 'Usuário') ?></span>
            <a href="<?= pixelhub_url('/logout') ?>">Sair</a>
        </div>
    </header>
    
    <div class="container">
        <nav class="sidebar">
            <?php
            // Função auxiliar para verificar se uma rota está ativa
            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            $isActive = function($patterns) use ($currentUri) {
                foreach ($patterns as $pattern) {
                    if (strpos($currentUri, $pattern) !== false) {
                        return true;
                    }
                }
                return false;
            };
            
            // Função auxiliar para verificar se um módulo deve estar expandido
            $shouldExpand = function($patterns) use ($currentUri) {
                foreach ($patterns as $pattern) {
                    if (strpos($currentUri, $pattern) !== false) {
                        return true;
                    }
                }
                return false;
            };
            ?>
            
            <!-- Dashboard (sem subitens) -->
            <a href="<?= pixelhub_url('/dashboard') ?>" class="sidebar-top-link <?= ($currentUri === '/' || strpos($currentUri, '/dashboard') !== false) ? 'active' : '' ?>">Dashboard</a>
            
            <!-- Clientes (sem subitens) -->
            <a href="<?= pixelhub_url('/tenants') ?>" class="sidebar-top-link <?= (strpos($currentUri, '/tenants') !== false) ? 'active' : '' ?>">Clientes</a>
            
            <!-- Tickets (sem subitens) -->
            <a href="<?= pixelhub_url('/tickets') ?>" class="sidebar-top-link <?= (strpos($currentUri, '/tickets') !== false) ? 'active' : '' ?>">Tickets</a>
            
            <!-- Agenda -->
            <?php
            $agendaActive = $isActive(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            $agendaExpanded = $shouldExpand(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            ?>
            <div class="sidebar-module" data-module="agenda">
                <div class="sidebar-module-header has-children <?= $agendaActive ? 'active' : '' ?> <?= $agendaExpanded ? 'is-open' : '' ?>">
                    Agenda
                </div>
                <div class="sidebar-module-content <?= $agendaExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/agenda') ?>" class="sub-item <?= (strpos($currentUri, '/agenda') !== false && strpos($currentUri, '/agenda/semana') === false && strpos($currentUri, '/agenda/stats') === false && strpos($currentUri, '/agenda/bloco') === false) ? 'active' : '' ?>">Agenda Diária</a>
                    <a href="<?= pixelhub_url('/agenda/semana') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/semana') !== false) ? 'active' : '' ?>">Agenda Semanal</a>
                    <a href="<?= pixelhub_url('/agenda/stats') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/stats') !== false) ? 'active' : '' ?>">Resumo Semanal</a>
                </div>
            </div>
            
            <!-- Financeiro -->
            <?php
            $financeiroActive = $isActive(['/billing/overview', '/billing/collections', '/recurring-contracts']);
            $financeiroExpanded = $shouldExpand(['/billing/overview', '/billing/collections', '/recurring-contracts']);
            ?>
            <div class="sidebar-module" data-module="financeiro">
                <div class="sidebar-module-header has-children <?= $financeiroActive ? 'active' : '' ?> <?= $financeiroExpanded ? 'is-open' : '' ?>">
                    Financeiro
                </div>
                <div class="sidebar-module-content <?= $financeiroExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/billing/overview') ?>" class="sub-item <?= (strpos($currentUri, '/billing/overview') !== false) ? 'active' : '' ?>">Central de Cobranças</a>
                    <a href="<?= pixelhub_url('/billing/collections') ?>" class="sub-item <?= (strpos($currentUri, '/billing/collections') !== false && strpos($currentUri, '/billing/overview') === false) ? 'active' : '' ?>">Histórico de Cobranças</a>
                    <a href="<?= pixelhub_url('/recurring-contracts') ?>" class="sub-item <?= (strpos($currentUri, '/recurring-contracts') !== false) ? 'active' : '' ?>">Carteira Recorrente</a>
                </div>
            </div>
            
            <!-- Serviços -->
            <?php
            $servicosActive = $isActive(['/hosting', '/hosting-plans']);
            $servicosExpanded = $shouldExpand(['/hosting', '/hosting-plans']);
            ?>
            <div class="sidebar-module" data-module="servicos">
                <div class="sidebar-module-header has-children <?= $servicosActive ? 'active' : '' ?> <?= $servicosExpanded ? 'is-open' : '' ?>">
                    Serviços
                </div>
                <div class="sidebar-module-content <?= $servicosExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/hosting') ?>" class="sub-item <?= (strpos($currentUri, '/hosting') !== false && strpos($currentUri, '/hosting-plans') === false) ? 'active' : '' ?>">Hospedagem & Cobranças</a>
                    <a href="<?= pixelhub_url('/hosting-plans') ?>" class="sub-item <?= (strpos($currentUri, '/hosting-plans') !== false) ? 'active' : '' ?>">Planos de Hospedagem</a>
                </div>
            </div>
            
            <!-- Projetos & Tarefas -->
            <?php
            $projetosActive = $isActive(['/projects/board', '/projects', '/screen-recordings']);
            $projetosExpanded = $shouldExpand(['/projects/board', '/projects', '/screen-recordings']);
            ?>
            <div class="sidebar-module" data-module="projetos">
                <div class="sidebar-module-header has-children <?= $projetosActive ? 'active' : '' ?> <?= $projetosExpanded ? 'is-open' : '' ?>">
                    Projetos & Tarefas
                </div>
                <div class="sidebar-module-content <?= $projetosExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/projects/board') ?>" class="sub-item <?= (strpos($currentUri, '/projects/board') !== false) ? 'active' : '' ?>">Quadro Kanban</a>
                    <a href="<?= pixelhub_url('/projects') ?>" class="sub-item <?= (strpos($currentUri, '/projects') !== false && strpos($currentUri, '/projects/board') === false) ? 'active' : '' ?>">Lista de Projetos</a>
                    <a href="<?= pixelhub_url('/screen-recordings') ?>" class="sub-item <?= (strpos($currentUri, '/screen-recordings') !== false) ? 'active' : '' ?>">Gravações de Tela</a>
                </div>
            </div>
            
            <!-- Minha Infraestrutura -->
            <?php
            $infraestruturaActive = $isActive(['/owner/shortcuts']);
            $infraestruturaExpanded = $shouldExpand(['/owner/shortcuts']);
            ?>
            <div class="sidebar-module" data-module="infraestrutura">
                <div class="sidebar-module-header has-children <?= $infraestruturaActive ? 'active' : '' ?> <?= $infraestruturaExpanded ? 'is-open' : '' ?>">
                    Minha Infraestrutura
                </div>
                <div class="sidebar-module-content <?= $infraestruturaExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/owner/shortcuts') ?>" class="sub-item <?= (strpos($currentUri, '/owner/shortcuts') !== false) ? 'active' : '' ?>">Acessos & Links</a>
                </div>
            </div>
            
            <!-- Configurações -->
            <?php
            $configuracoesActive = $isActive(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/diagnostic/financial']);
            $configuracoesExpanded = $shouldExpand(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/diagnostic/financial']);
            ?>
            <div class="sidebar-module" data-module="configuracoes">
                <div class="sidebar-module-header has-children <?= $configuracoesActive ? 'active' : '' ?> <?= $configuracoesExpanded ? 'is-open' : '' ?>">
                    Configurações
                </div>
                <div class="sidebar-module-content <?= $configuracoesExpanded ? 'is-open' : '' ?>">
                    <div class="sidebar-internal-title">Diagnóstico</div>
                    <a href="<?= pixelhub_url('/diagnostic/financial') ?>" class="sub-item <?= (strpos($currentUri, '/diagnostic/financial') !== false) ? 'active' : '' ?>">Financeiro</a>
                    <div class="sidebar-internal-title">Financeiro</div>
                    <a href="<?= pixelhub_url('/billing/service-types') ?>" class="sub-item <?= (strpos($currentUri, '/billing/service-types') !== false) ? 'active' : '' ?>">Categorias de Contratos</a>
                    <div class="sidebar-internal-title">Mensagens</div>
                    <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-templates') !== false) ? 'active' : '' ?>">Mensagens WhatsApp</a>
                    <div class="sidebar-internal-title">Infraestrutura</div>
                    <a href="<?= pixelhub_url('/settings/hosting-providers') ?>" class="sub-item <?= (strpos($currentUri, '/settings/hosting-providers') !== false) ? 'active' : '' ?>">Provedores de Hospedagem</a>
                </div>
            </div>
        </nav>
        
        <main class="content">
            <?= $content ?? '' ?>
        </main>
    </div>
    
    <script>
        /**
         * Sistema de Accordion para o Menu Lateral
         * 
         * Funcionalidade:
         * - Cada módulo de topo (com subitens) pode ser expandido/recolhido
         * - Apenas um módulo pode estar expandido por vez
         * - O módulo que contém a página atual é expandido automaticamente
         * 
         * Classes utilizadas:
         * - .sidebar-module: Container do módulo
         * - .sidebar-module-header: Cabeçalho clicável do módulo
         * - .sidebar-module-header.has-children: Indica que tem subitens
         * - .sidebar-module-header.is-open: Módulo expandido
         * - .sidebar-module-header.active: Módulo contém página ativa
         * - .sidebar-module-content: Container dos subitens
         * - .sidebar-module-content.is-open: Subitens visíveis
         */
        (function() {
            // Encontra todos os módulos do menu
            const modules = document.querySelectorAll('.sidebar-module');
            
            // Para cada módulo, adiciona listener de clique no cabeçalho
            modules.forEach(function(module) {
                const header = module.querySelector('.sidebar-module-header');
                const content = module.querySelector('.sidebar-module-content');
                
                // Só adiciona listener se o módulo tiver subitens
                if (header && content && header.classList.contains('has-children')) {
                    header.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Verifica se o módulo clicado já está aberto
                        const isCurrentlyOpen = header.classList.contains('is-open');
                        
                        // Fecha todos os módulos
                        modules.forEach(function(otherModule) {
                            const otherHeader = otherModule.querySelector('.sidebar-module-header');
                            const otherContent = otherModule.querySelector('.sidebar-module-content');
                            
                            if (otherHeader && otherContent) {
                                otherHeader.classList.remove('is-open');
                                otherContent.classList.remove('is-open');
                            }
                        });
                        
                        // Se o módulo clicado não estava aberto, abre ele
                        if (!isCurrentlyOpen) {
                            header.classList.add('is-open');
                            content.classList.add('is-open');
                        }
                    });
                }
            });
        })();
    </script>
    
    <!-- Script do gravador de tela (global) -->
    <script src="<?= pixelhub_url('/assets/js/screen-recorder.js') ?>"></script>
    
    <!-- Função global para iniciar gravação com contexto inteligente -->
    <script>
        /**
         * Inicia gravação de tela com contexto inteligente
         * - Se estiver na página /screen-recordings: abre em modo LIBRARY (salva na biblioteca)
         * - Se houver currentTaskId: abre em modo TAREFA (anexa na task)
         * - Se não houver: abre em modo RÁPIDO (apenas download)
         */
        function startGlobalScreenRecording() {
            if (!window.PixelHubScreenRecorder) {
                alert('Gravador de tela não está disponível no momento.');
                return;
            }

            // Detecta se estamos na página de Gravações de Tela
            const currentPath = window.location.pathname;
            const isOnScreenRecordingsPage = currentPath.includes('/screen-recordings') && !currentPath.includes('/screen-recordings/share');
            
            if (isOnScreenRecordingsPage) {
                // Na página de Gravações de Tela, sempre usa modo library
                console.log('[ScreenRecorder] startGlobalScreenRecording: modo biblioteca (página Gravações de Tela)');
                window.currentTaskId = null;
                window.PixelHubScreenRecorder.open(null, 'library');
                return;
            }

            // Verifica se realmente há uma tarefa válida em foco
            // (não apenas se currentTaskId existe, mas se é um número válido)
            const taskId = window.currentTaskId;
            if (taskId && typeof taskId === 'number' && taskId > 0) {
                // Modo tarefa: vincula à tarefa atual
                console.log('[ScreenRecorder] startGlobalScreenRecording: modo tarefa, taskId=', taskId);
                window.PixelHubScreenRecorder.open(taskId, 'task');
            } else {
                // Modo rápido: apenas download, sem tarefa
                console.log('[ScreenRecorder] startGlobalScreenRecording: modo rápido (sem tarefa)');
                window.PixelHubScreenRecorder.open(null, 'quick');
            }
        }
    </script>
</body>
</html>

