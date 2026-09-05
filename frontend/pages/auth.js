import { checkSession, logout, getMessages } from '../api/index.js';

let currentUser = null;
let messageBadgeInstalled = false;
let adminLinkInstalled = false;

const installAdminDashboardLink = (userNav, user) => {
    if (!userNav || !user || adminLinkInstalled) return;
    adminLinkInstalled = true;
    if (user.role !== 'admin') return;
    const logoutBtn = userNav.querySelector('#logout-btn');
    const existing = userNav.querySelector('a[href="admin/dashboard.html"]');
    if (existing) return;
    const link = document.createElement('a');
    link.href = 'admin/dashboard.html';
    link.className = 'messages-nav-link';
    link.textContent = '管理后台';
    if (logoutBtn) {
        logoutBtn.insertAdjacentElement('beforebegin', link);
    } else {
        userNav.appendChild(link);
    }
};

const installMessageUnreadBadge = async (userNav) => {
    if (!userNav || messageBadgeInstalled) return;
    messageBadgeInstalled = true;
    let messagesLink = userNav.querySelector('a[href="messages.html"]');
    if (!messagesLink) {
        messagesLink = document.createElement('a');
        messagesLink.href = 'messages.html';
        messagesLink.className = 'messages-nav-link';
        messagesLink.textContent = '消息中心';
        const logoutBtn = userNav.querySelector('#logout-btn');
        if (logoutBtn) {
            logoutBtn.insertAdjacentElement('beforebegin', messagesLink);
        } else {
            userNav.appendChild(messagesLink);
        }
    }
    messagesLink.classList.add('messages-nav-link');
    const wrapper = document.createElement('span');
    wrapper.className = 'messages-nav-wrapper';
    messagesLink.parentNode.insertBefore(wrapper, messagesLink);
    wrapper.appendChild(messagesLink);
    let badge = document.createElement('span');
    badge.className = 'message-unread-dot';
    badge.style.display = 'none';
    wrapper.appendChild(badge);
    const refreshBadge = async () => {
        try {
            const res = await getMessages();
            const total = (res && res.data) ? res.data.length : 0;
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : String(total);
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        } catch (_) {
            badge.style.display = 'none';
        }
    };
    refreshBadge();
    setInterval(refreshBadge, 45 * 1000);
};

/**
 * Sets up the header navigation based on user login status.
 * @param {object|null} user - The user object or null if not logged in.
 */
function setupHeader(user) {
    const userNav = document.getElementById('user-nav');
    const guestNav = document.getElementById('guest-nav');
    const profileLink = document.getElementById('profile-link');

    if (user) {
        // 用户已登录
        currentUser = user;
        
        if (profileLink) {
            profileLink.textContent = user.username;
        }

        // 显示已登录导航，隐藏未登录导航
        if (userNav) {
            userNav.style.display = 'flex';
            installAdminDashboardLink(userNav, user);
            installMessageUnreadBadge(userNav);
            // 设置登出按钮事件
            const logoutBtn = userNav.querySelector('#logout-btn');
        if (logoutBtn) {
                // 移除所有已有的点击事件
                const newLogoutBtn = logoutBtn.cloneNode(true);
                logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);
                
                // 添加新的点击事件
                newLogoutBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    await logout();
                    window.location.href = 'login.html';
                } catch (error) {
                    alert('登出失败，请稍后重试。');
                }
            });
            }
        }
        
        if (guestNav) {
            guestNav.style.display = 'none';
        }
    } else {
        // 用户未登录
        currentUser = null;
        
        // 隐藏已登录导航，显示未登录导航
        if (userNav) {
            userNav.style.display = 'none';
        }
        
        if (guestNav) {
            guestNav.style.display = 'flex';
        }
    }
}


/**
 * Checks the user's session status and updates the header accordingly.
 * Can optionally redirect to login if the user is not authenticated.
 * @param {boolean} redirect - If true, redirects to login.html if not authenticated.
 */
export async function checkSessionAndSetupHeader(redirect = false) {
    try {
        const result = await checkSession();
        if (result.success && result.data.isLoggedIn) {
            setupHeader(result.data.user);
            return result.data.user;
        } else {
            setupHeader(null);
            if (redirect) {
                window.location.href = 'login.html';
            }
            return null;
        }
    } catch (error) {
        console.error('Session check failed:', error);
        setupHeader(null);
        if (redirect) {
            window.location.href = 'login.html';
        }
        return null;
    }
}

export function getCurrentUser() {
    return currentUser;
} 