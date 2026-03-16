import { checkSession, logout } from '../api/index.js';

let currentUser = null;

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