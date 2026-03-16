import { forgotPasswordStep1, forgotPasswordStep2 } from "../api/index.js";
import { showMessage, hideMessage } from "../utils/dom.js";

document.addEventListener("DOMContentLoaded", () => {
    const formStep1 = document.getElementById("forgot-form-step1");
    const formStep2 = document.getElementById("forgot-form-step2");
    const resetSection = document.getElementById("reset-section");
    const messageBox = document.getElementById("message-box");
    const emailInput = document.getElementById("email");
    const questionDisplay = document.getElementById("security-question-display");
    const newPasswordInput = document.getElementById("new-password");
    const confirmNewPasswordInput = document.getElementById("confirm-new-password");
    
    // Initially hide step 2
    resetSection.style.display = 'none';
    let userEmail = ''; // To store email from step 1

    formStep1.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideMessage(messageBox);
        userEmail = emailInput.value;

        if (!userEmail) {
            showMessage(messageBox, "error", "请输入邮箱地址。");
            return;
        }

        try {
            const result = await forgotPasswordStep1({ email: userEmail });
            if (result.success) {
                questionDisplay.textContent = result.data.question;
                formStep1.style.display = 'none';
                resetSection.style.display = 'block';
            } else {
                showMessage(messageBox, "error", result.message);
            }
        } catch (error) {
            console.error("获取安全问题失败:", error);
            showMessage(messageBox, "error", error.message || "请求失败，请检查网络并重试。");
        }
    });

    formStep2.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideMessage(messageBox);

        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmNewPasswordInput.value;

        if (newPassword !== confirmPassword) {
            showMessage(messageBox, "error", "两次输入的新密码不一致。");
            return;
        }

        const answer = document.getElementById("security-answer").value;
        const verificationCode = document.getElementById("verification-code").value; // 获取验证码

        try {
            const formData = new FormData();
            formData.append('answer', answer);
            formData.append('new_password', newPassword);
            formData.append('verification_code', verificationCode); // 添加验证码

            const response = await fetch('../backend/api/users/forgot_password_step2.php', {
                method: 'POST',
                body: formData
            });
            if (response.ok) {
                showMessage(messageBox, "success", "密码重置成功！正在跳转到登录页面...");
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                const result = await response.json();
                showMessage(messageBox, "error", result.message);
            }
        } catch (error) {
            console.error("重置密码失败:", error);
            showMessage(messageBox, "error", error.message || "请求失败，请检查网络并重试。");
        }
    });
}); 