import { register } from '../api/index.js';
import { showMessage } from '../utils/dom.js';
import { $, $$ } from '../utils/dom.js';

const form = document.getElementById('register-form');

document.addEventListener('DOMContentLoaded', () => {
    const registerForm = $('#register-form');
    const messageDiv = $('#message-box');
    let userEmail = ''; // 用于存储注册邮箱

    const verificationSection = $('#verification-section');
    const verificationForm = $('#verification-form');
    const verificationMessageDiv = $('#verification-message');
    const verificationEmailDisplay = $('#verification-email-display');
    const resendBtn = $('#resend-code-btn');

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (messageDiv) messageDiv.textContent = '';

            const formData = new FormData(registerForm);
            userEmail = formData.get('email'); // 保存邮箱

            // 检查密码是否一致
            if (formData.get('password') !== formData.get('confirm_password')) {
                if (messageDiv) {
                    messageDiv.textContent = '两次输入的密码不匹配！';
                    messageDiv.className = 'message error';
                }
                return;
            }

            try {
                const response = await fetch('../backend/api/users/register.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    if (messageDiv) {
                        messageDiv.textContent = result.message;
                        messageDiv.className = 'message success';
                    }
                    // 注册成功，隐藏注册表单，显示验证表单
                    registerForm.style.display = 'none';
                    verificationSection.style.display = 'block';
                    verificationEmailDisplay.textContent = `邮箱: ${userEmail}`;
                } else {
                    if (messageDiv) {
                        messageDiv.textContent = result.message || '注册失败，请稍后再试。';
                        messageDiv.className = 'message error';
                    }
                }
            } catch (error) {
                console.error('注册请求失败:', error);
                if (messageDiv) {
                    messageDiv.textContent = '注册请求失败，请检查网络连接。';
                    messageDiv.className = 'message error';
                }
            }
        });
    }

    if (verificationForm) {
        verificationForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            verificationMessageDiv.textContent = '';

            const code = $('#verification-code').value;

            if (!userEmail || !code) {
                verificationMessageDiv.textContent = '无法获取邮箱或验证码。';
                verificationMessageDiv.className = 'message error';
                return;
            }

            try {
                const response = await fetch('../backend/api/auth/verify_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail, code: code })
                });

                const result = await response.json();

                if (result.success) {
                    verificationMessageDiv.textContent = result.message + ' 即将跳转到登录页面...';
                    verificationMessageDiv.className = 'message success';
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 3000);
                } else {
                    verificationMessageDiv.textContent = result.message || '验证失败。';
                    verificationMessageDiv.className = 'message error';
                }
            } catch (error) {
                console.error('验证请求失败:', error);
                verificationMessageDiv.textContent = '验证请求失败，请检查网络连接。';
                verificationMessageDiv.className = 'message error';
            }
        });
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            verificationMessageDiv.textContent = '正在发送...';
            verificationMessageDiv.className = 'message';

            if (!userEmail) {
                verificationMessageDiv.textContent = '无法获取邮箱地址，请刷新页面重试。';
                verificationMessageDiv.className = 'message error';
                return;
            }

            try {
                const response = await fetch('../backend/api/auth/resend_verification_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail })
                });

                const result = await response.json();

                if (result.success) {
                    verificationMessageDiv.textContent = result.message;
                    verificationMessageDiv.className = 'message success';
                } else {
                    verificationMessageDiv.textContent = result.message || '重发失败。';
                    verificationMessageDiv.className = 'message error';
                }
            } catch (error) {
                console.error('重发请求失败:', error);
                verificationMessageDiv.textContent = '请求失败，请检查网络连接。';
                verificationMessageDiv.className = 'message error';
            }
        });
    }
}); 