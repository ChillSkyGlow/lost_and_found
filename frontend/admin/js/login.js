import { $, showMessage } from '../../utils/dom.js';

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = $('#admin-login-form');
    const messageBox = $('#message-box');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        showMessage(messageBox, 'info', '正在登录...');

        const username = $('#username').value;
        const password = $('#password').value;

        try {
            const response = await fetch('../../backend/api/admin/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });

            const result = await response.json();

            if (result.success) {
                showMessage(messageBox, 'success', '登录成功！正在跳转到主控台...');
                window.location.href = 'dashboard.html';
            } else {
                showMessage(messageBox, 'error', result.message || '登录失败。');
            }
        } catch (error) {
            showMessage(messageBox, 'error', '网络请求失败或服务器无响应。');
            console.error('Admin login error:', error);
        }
    });
}); 