import {
    getUserInfo,
    updateProfile,
    changePassword,
    changeSecurityQuestion,
    logout,
} from "../api/index.js";
import { checkSessionAndSetupHeader } from "./auth.js";
import { showMessage, hideMessage } from "../utils/dom.js";

document.addEventListener("DOMContentLoaded", async () => {
    const session = await checkSessionAndSetupHeader();
    if (!session) return;

    // --- Profile Form ---
    const profileForm = document.getElementById("update-profile-form");
    const profileMessageBox = document.getElementById("profile-message-box");
    const usernameInput = document.getElementById("username");
    const realNameInput = document.getElementById("real_name");
    const studentIdInput = document.getElementById("student_id");
    const phoneInput = document.getElementById("phone");
    const emailInput = document.getElementById("email");

    // --- Password Form ---
    const passwordForm = document.getElementById("change-password-form");
    const passwordMessageBox = document.getElementById("password-message-box");

    // --- Security Form ---
    const securityForm = document.getElementById("change-security-form");
    const securityMessageBox = document.getElementById("security-message-box");
    const currentQuestionDisplay = document.getElementById("current-security-question");

    // Load initial user data
    const loadUserInfo = async () => {
        try {
            const result = await getUserInfo();
            if (result.success) {
                usernameInput.value = result.data.username || '';
                realNameInput.value = result.data.real_name || '';
                studentIdInput.value = result.data.student_id || '';
                phoneInput.value = result.data.phone || '';
                emailInput.value = result.data.email;
                currentQuestionDisplay.textContent = result.data.security_question;
            } else {
                // Handle error loading user info
                showMessage(profileMessageBox, "error", "无法加载用户信息。");
            }
        } catch (error) {
            console.error("加载用户信息失败:", error);
            showMessage(profileMessageBox, "error", "网络错误，无法加载用户信息。");
        }
    };
    
    // --- Event Handlers ---

    profileForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideMessage(profileMessageBox);
        try {
            const result = await updateProfile({
                username: usernameInput.value,
                email: emailInput.value,
                real_name: realNameInput.value,
                student_id: studentIdInput.value,
                phone: phoneInput.value
            });
            if (result.success) {
                showMessage(profileMessageBox, "success", "个人资料更新成功！即将自动登出...");
                setTimeout(async () => {
                    await logout();
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showMessage(profileMessageBox, "error", result.message);
            }
        } catch (error) {
            showMessage(profileMessageBox, "error", "更新失败，请重试。");
        }
    });

    passwordForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideMessage(passwordMessageBox);
        const currentPassword = e.target.current_password.value;
        const newPassword = e.target.new_password.value;
        const confirmPassword = e.target["confirm-new-password"].value;

        if (newPassword !== confirmPassword) {
            showMessage(passwordMessageBox, "error", "新密码和确认密码不匹配。");
            return;
        }

        try {
            const result = await changePassword({
                current_password: currentPassword,
                new_password: newPassword
            });
            if (result.success) {
                showMessage(passwordMessageBox, "success", "密码修改成功！即将自动登出...");
                passwordForm.reset();
                setTimeout(async () => {
                    await logout();
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showMessage(passwordMessageBox, "error", result.message);
            }
        } catch (error) {
            showMessage(passwordMessageBox, "error", "修改失败，请重试。");
        }
    });

    securityForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideMessage(securityMessageBox);
        const currentPassword = e.target.current_password.value;
        const newQuestion = e.target.new_question.value;
        const newAnswer = e.target.new_answer.value;

        try {
            const result = await changeSecurityQuestion({
                current_password: currentPassword,
                new_question: newQuestion,
                new_answer: newAnswer
            });
            if (result.success) {
                showMessage(securityMessageBox, "success", "安全问题修改成功！即将自动登出...");
                securityForm.reset();
                setTimeout(async () => {
                    await logout();
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showMessage(securityMessageBox, "error", result.message);
            }
        } catch (error) {
            showMessage(securityMessageBox, "error", "修改失败，请重试。");
        }
    });

    // Initial load
    loadUserInfo();
}); 