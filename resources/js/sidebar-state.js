/**
 * avali.ai - Gerenciador de Persistência Segura da Sidebar e Submenus
 * 
 * Responsável por salvar e restaurar o estado de abertura/fechamento dos grupos
 * e da própria barra lateral no localStorage, com validação de esquema estrita contra XSS/injection.
 */

const STORAGE_KEY_GROUPS = 'avali.sidebar.groups';
const STORAGE_KEY_SIDEBAR = 'flux-sidebar-collapsed-desktop';
const ALLOWED_GROUPS = new Set(['admin', 'turmas', 'provas', 'aulas']);

/**
 * Lê e sanitiza o estado salvo no localStorage para os grupos
 * @returns {Record<string, boolean>}
 */
function getStoredGroups() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY_GROUPS);
        if (!raw) return {};

        const parsed = JSON.parse(raw);
        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
            return {};
        }

        // Sanitização rigorosa: apenas chaves permitidas e valores estritamente booleanos
        const sanitized = {};
        for (const [key, value] of Object.entries(parsed)) {
            if (ALLOWED_GROUPS.has(key) && typeof value === 'boolean') {
                sanitized[key] = value;
            }
        }
        return sanitized;
    } catch {
        return {};
    }
}

/**
 * Salva o estado atual de um grupo no localStorage com segurança
 * @param {string} groupKey
 * @param {boolean} isOpen
 */
function setStoredGroupState(groupKey, isOpen) {
    if (!ALLOWED_GROUPS.has(groupKey) || typeof isOpen !== 'boolean') {
        return;
    }

    try {
        const current = getStoredGroups();
        current[groupKey] = isOpen;
        localStorage.setItem(STORAGE_KEY_GROUPS, JSON.stringify(current));
    } catch {
        // Ignora em caso de QuotaExceededError ou Storage bloqueado
    }
}

/**
 * Lê o estado de recolhimento da barra lateral
 * @returns {boolean}
 */
function isSidebarCollapsed() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY_SIDEBAR);
        return raw !== null ? JSON.parse(raw) === true : false;
    } catch {
        return false;
    }
}

/**
 * Aplica o estado da barra lateral (recolhida ou expandida)
 */
function applySidebarCollapsedState() {
    const sidebar = document.querySelector('ui-sidebar');
    if (!sidebar) return;

    const isCollapsed = isSidebarCollapsed();
    const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

    if (isDesktop && isCollapsed) {
        sidebar.setAttribute('data-flux-sidebar-collapsed-desktop', '');
        if (sidebar.state) {
            sidebar.state.collapsedDesktop = true;
        }
    } else if (isDesktop && !isCollapsed) {
        sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
        if (sidebar.state) {
            sidebar.state.collapsedDesktop = false;
        }
    }
}

/**
 * Aplica o estado restaurado nos elementos ui-disclosure correspondentes
 */
function applySidebarState() {
    // 1. Aplica o estado de colapso geral da sidebar
    applySidebarCollapsedState();

    // 2. Aplica o estado de abertura dos grupos
    const stored = getStoredGroups();

    document.querySelectorAll('[data-sidebar-group]').forEach(container => {
        const groupKey = container.getAttribute('data-sidebar-group');
        if (!groupKey || !ALLOWED_GROUPS.has(groupKey)) return;

        // Se o usuário já interagiu e salvou um estado para este grupo
        if (Object.prototype.hasOwnProperty.call(stored, groupKey)) {
            const shouldBeOpen = stored[groupKey];
            const disclosure = container.matches('ui-disclosure') 
                ? container 
                : container.querySelector('ui-disclosure');

            if (!disclosure) return;

            const button = disclosure.querySelector('button, ui-button');
            const panel = disclosure.lastElementChild;

            if (shouldBeOpen) {
                disclosure.setAttribute('open', '');
                disclosure.setAttribute('data-open', '');
                if (button) button.setAttribute('aria-expanded', 'true');
                if (panel) panel.setAttribute('data-open', '');
                if (disclosure._disclosable) {
                    disclosure._disclosable.setState(true);
                }
            } else {
                disclosure.removeAttribute('open');
                disclosure.removeAttribute('data-open');
                if (button) button.setAttribute('aria-expanded', 'false');
                if (panel) panel.removeAttribute('data-open');
                if (disclosure._disclosable) {
                    disclosure._disclosable.setState(false);
                }
            }
        }
    });
}

/**
 * Registra ouvintes para capturar a alternância do usuário
 */
function setupSidebarListeners() {
    // Monitora alternância global da sidebar pelo botão de collapse
    const sidebar = document.querySelector('ui-sidebar');
    if (sidebar && !sidebar._hasCollapseListener) {
        // Observa alterações no atributo data-flux-sidebar-collapsed-desktop
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.attributeName === 'data-flux-sidebar-collapsed-desktop') {
                    const isCollapsed = sidebar.hasAttribute('data-flux-sidebar-collapsed-desktop');
                    try {
                        localStorage.setItem(STORAGE_KEY_SIDEBAR, JSON.stringify(isCollapsed));
                    } catch {}
                }
            }
        });

        observer.observe(sidebar, { attributes: true });
        sidebar._hasCollapseListener = true;
    }

    // Monitora submenus
    document.querySelectorAll('[data-sidebar-group]').forEach(container => {
        const groupKey = container.getAttribute('data-sidebar-group');
        if (!groupKey || !ALLOWED_GROUPS.has(groupKey)) return;

        const disclosure = container.matches('ui-disclosure') 
            ? container 
            : container.querySelector('ui-disclosure');

        if (!disclosure || disclosure._hasStateListener) return;

        // Ouve eventos do Flux UI (lofi-disclosable-change) e cliques no botão
        disclosure.addEventListener('lofi-disclosable-change', () => {
            const isOpen = disclosure.hasAttribute('open') || disclosure.hasAttribute('data-open');
            setStoredGroupState(groupKey, isOpen);
        });

        const button = disclosure.querySelector('button, ui-button');
        if (button) {
            button.addEventListener('click', () => {
                setTimeout(() => {
                    const isOpen = disclosure.hasAttribute('open') || disclosure.hasAttribute('data-open');
                    setStoredGroupState(groupKey, isOpen);
                }, 50);
            });
        }

        disclosure._hasStateListener = true;
    });
}

/**
 * Inicialização e integração com Livewire SPA
 */
function initSidebarPersistence() {
    applySidebarState();
    setupSidebarListeners();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarPersistence);
} else {
    initSidebarPersistence();
}

// Suporte a Livewire SPA e navegação instantânea
document.addEventListener('livewire:navigated', () => {
    initSidebarPersistence();
    // Executa também após o ciclo microtask para garantir sincronização com o Flux
    setTimeout(applySidebarState, 10);
});

// Executa imediatamente quando o elemento ui-sidebar for montado/renderizado
window.addEventListener('load', applySidebarState);
