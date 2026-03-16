import { $, showMessage } from '../../utils/dom.js';

document.addEventListener('DOMContentLoaded', async () => {
    const tablesNav = $('#tables-nav');
    const tableTitle = $('#table-title');
    const dataTableContainer = $('#data-table-container');
    const messageBox = $('#message-box');
    const logoutBtn = $('#logout-btn');

    // 模态框元素
    const editModal = $('#edit-modal');
    const editForm = $('#edit-form');
    const closeModalBtn = $('.modal-close-btn');

    let currentTableData = []; // 用于存储当前表格的数据
    let currentTableHeaders = [];
    let currentTableName = '';
    let currentPrimaryKey = '';

    // 鉴权函数
    const checkAdminAuth = async () => {
        // 在实际应用中，这里应该有一个后端接口验证会话
        // 为简化，我们暂时假设会话有效，但在真实API调用失败时会处理401错误
        return true;
    };

    // 渲染表格数据
    const renderTable = (tableName, primaryKey, headers, rows) => {
        if (rows.length === 0) {
            dataTableContainer.innerHTML = '<p>该表没有数据。</p>';
            return;
        }
        let html = '<table><thead><tr>';
        headers.forEach(h => html += `<th>${h}</th>`);
        html += '<th>操作</th>'; // 添加操作列
        html += '</tr></thead><tbody>';

        rows.forEach(row => {
            html += `<tr data-pk-value="${row[primaryKey]}">`; // 给行添加主键值
            headers.forEach(h => {
                let cellValue = row[h] === null ? 'NULL' : row[h];
                // 防止过长的内容破坏布局
                if (typeof cellValue === 'string' && cellValue.length > 100) {
                    cellValue = cellValue.substring(0, 100) + '...';
                }
                html += `<td>${cellValue}</td>`;
            });
            // 添加按钮
            html += `<td>
                <button class="button button-edit" data-table="${tableName}" data-pk-name="${primaryKey}" data-pk-value="${row[primaryKey]}">修改</button>
                <button class="button button-danger button-delete" data-table="${tableName}" data-pk-name="${primaryKey}" data-pk-value="${row[primaryKey]}">删除</button>
            </td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';
        dataTableContainer.innerHTML = html;
    };

    const openEditModal = (rowData) => {
        let formHtml = '';
        currentTableHeaders.forEach(header => {
            const value = rowData[header] || '';
            formHtml += `<div class="form-group">
                <label for="edit-${header}">${header}</label>`;
            // 如果是主键，则设为只读
            if (header === currentPrimaryKey) {
                formHtml += `<input type="text" id="edit-${header}" name="${header}" value="${value}" readonly>`;
            } else if (value.length > 100) { // 如果内容太长，使用textarea
                formHtml += `<textarea id="edit-${header}" name="${header}">${value}</textarea>`;
            } else {
                formHtml += `<input type="text" id="edit-${header}" name="${header}" value="${value}">`;
            }
            formHtml += `</div>`;
        });
        formHtml += `<button type="submit" class="button">保存更改</button>`;
        editForm.innerHTML = formHtml;
        editModal.style.display = 'flex';
    };

    // 获取并显示指定表的数据
    const fetchAndDisplayTableData = async (tableName) => {
        tableTitle.textContent = `正在加载 ${tableName}...`;
        dataTableContainer.innerHTML = '';
        try {
            const response = await fetch(`../../backend/api/admin/get_table_data.php?table=${tableName}`);
            if (response.status === 401) {
                window.location.href = 'index.html';
                return;
            }
            const result = await response.json();
            if (result.success) {
                tableTitle.textContent = `表: ${tableName}`;
                currentTableData = result.data.data;
                currentPrimaryKey = result.data.primary_key;
                currentTableName = tableName;

                if (currentTableData.length > 0) {
                    currentTableHeaders = Object.keys(currentTableData[0]);
                    renderTable(tableName, currentPrimaryKey, currentTableHeaders, currentTableData);
                } else {
                    dataTableContainer.innerHTML = '<p>该表没有数据。</p>';
                }
            } else {
                showMessage(messageBox, 'error', result.message);
            }
        } catch (error) {
            showMessage(messageBox, 'error', '请求数据失败。');
        }
    };

    // 使用事件委托处理所有按钮的点击
    dataTableContainer.addEventListener('click', async (e) => {
        if (e.target.classList.contains('button-delete')) {
            const btn = e.target;
            const tableName = btn.dataset.table;
            const pkName = btn.dataset.pkName;
            const pkValue = btn.dataset.pkValue;

            if (!confirm(`您确定要从表 [${tableName}] 中删除ID为 [${pkValue}] 的记录吗？此操作无法撤销。`)) {
                return;
            }

            try {
                const response = await fetch('../../backend/api/admin/delete_row.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table: tableName, pk_name: pkName, pk_value: pkValue })
                });
                const result = await response.json();
                if (result.success) {
                    showMessage(messageBox, 'success', result.message);
                    // 从DOM中移除该行
                    btn.closest('tr').remove();
                } else {
                    showMessage(messageBox, 'error', result.message);
                }
            } catch (error) {
                showMessage(messageBox, 'error', '删除请求失败。');
            }
        } else if (e.target.classList.contains('button-edit')) {
            const pkValue = e.target.dataset.pkValue;
            const rowData = currentTableData.find(row => row[currentPrimaryKey] == pkValue);
            if (rowData) {
                openEditModal(rowData);
            }
        }
    });

    // 关闭模态框
    closeModalBtn.addEventListener('click', () => editModal.style.display = 'none');
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) { // 点击遮罩层关闭
            editModal.style.display = 'none';
        }
    });

    // 处理编辑表单提交
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(editForm);
        const updatedRowData = {};
        formData.forEach((value, key) => {
            updatedRowData[key] = value;
        });

        const pkValue = updatedRowData[currentPrimaryKey];

        try {
            const response = await fetch('../../backend/api/admin/update_row.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table: currentTableName,
                    pk_name: currentPrimaryKey,
                    pk_value: pkValue,
                    row_data: updatedRowData
                })
            });
            const result = await response.json();
            if (result.success) {
                showMessage(messageBox, 'success', '更新成功！');
                editModal.style.display = 'none';
                // 刷新当前表格数据
                fetchAndDisplayTableData(currentTableName);
            } else {
                alert('更新失败: ' + result.message);
            }
        } catch (error) {
            alert('请求失败: ' + error);
        }
    });

    // 初始化函数
    const init = async () => {
        if (!await checkAdminAuth()) {
            window.location.href = 'index.html';
            return;
        }

        try {
            const response = await fetch('../../backend/api/admin/get_tables.php');
            if (response.status === 401) { // 捕获未授权的跳转
                window.location.href = 'index.html';
                return;
            }
            const result = await response.json();
            if (result.success) {
                tablesNav.innerHTML = result.data.map(table => `<a href="#" data-table="${table}">${table}</a>`).join('');
                
                // 为侧边栏的每个链接添加点击事件
                tablesNav.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        tablesNav.querySelectorAll('a').forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                        const tableName = link.dataset.table;
                        fetchAndDisplayTableData(tableName);
                    });
                });
            } else {
                showMessage(messageBox, 'error', '无法加载数据库表列表。');
            }
        } catch (error) {
            showMessage(messageBox, 'error', '请求表列表失败。');
        }
    };

    // 退出登录
    logoutBtn.addEventListener('click', () => {
        // 这里应调用后端登出接口
        // 为简化，直接清除会话相关的标识并跳转
        // TODO: 创建后端 /admin/logout.php
        showMessage(messageBox, 'success', '正在退出...');
        window.location.href = 'index.html';
    });

    init();
}); 