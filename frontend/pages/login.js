import { login } from "../api/index.js";
import { showMessage } from "../utils/dom.js";

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('login-form');
    const messageBox = document.getElementById('message-box');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const credentials = {
                username: form.username.value,
                password: form.password.value
            };

            try {
                const result = await login(credentials);
                
                if (result.success) {
                    showMessage(messageBox, 'success', result.message + ' 即将跳转到主页...');
                    setTimeout(() => window.location.href = 'index.html', 1500);
                } else {
                    showMessage(messageBox, 'error', result.message);
                }
            } catch (error) {
                console.error("登录失败:", error);
                showMessage(messageBox, 'error', error.message || '网络请求失败，请稍后重试。');
            }
        });
    }
}); 