import { $, showMessage } from '../utils/dom.js';

document.addEventListener('DOMContentLoaded', () => {
    const sendCodeBtn = $('#send-code-btn');
    const deleteForm = $('#delete-form');
    const messageBox = $('#message-box');

    // 1. 处理发送验证码按钮点击
    sendCodeBtn.addEventListener('click', async () => {
        showMessage(messageBox, 'info', '正在发送验证码...');
        sendCodeBtn.disabled = true;

        try {
            const response = await fetch('../backend/api/users/request_delete_code.php', {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                showMessage(messageBox, 'success', result.message);
                deleteForm.style.display = 'block'; // 显示第二步表单
                sendCodeBtn.style.display = 'none'; // 隐藏第一步按钮
            } else {
                showMessage(messageBox, 'error', result.message || '发送失败，请刷新页面重试。');
                sendCodeBtn.disabled = false;
            }
        } catch (error) {
            showMessage(messageBox, 'error', '网络请求失败，请稍后重试。');
            sendCodeBtn.disabled = false;
        }
    });

    // 2. 处理确认注销表单提交
    deleteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = $('#verification-code').value;
        if (!code) {
            showMessage(messageBox, 'error', '请输入验证码。');
            return;
        }

        if (!confirm('您确定要永久注销您的账户吗？此操作无法撤销！')) {
            return;
        }

        showMessage(messageBox, 'info', '正在处理您的请求...');

        try {
            const formData = new FormData();
            formData.append('code', code);

            const response = await fetch('../backend/api/users/confirm_delete.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showMessage(messageBox, 'success', '账户已成功注销。您将返回首页。');
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 3000);
            } else {
                showMessage(messageBox, 'error', result.message || '注销失败，请检查您的验证码。');
            }
        } catch (error) {
            showMessage(messageBox, 'error', '网络请求失败，请稍后重试。');
        }
    });
}); 