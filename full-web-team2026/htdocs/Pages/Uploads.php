<?php include '../Controllers/Header.php';
if (!$Login || empty($ImS) || $ImS['isQuanTriVien'] != 1) {
    echo '<script>window.location.href = "/Forum";</script>';
    exit();
} ?>
<div class="body">
    <div id="box_forums">
        <div class="box_list_parent">
            <div class="box_parent_list_next">
                <div class="box_phantrang">
                    <div class="backlink">
                        <a style="color:#fff;" href="/Forum">Quay lại</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <section class="upload-post" style="text-align:center; padding:20px;">
        <h1 class="title" style="margin-bottom:10px; font-size:24px;">Đăng Bài Viết</h1>
        <hr style="border-top:1px solid #ccc; margin-bottom:20px;">
        <form id="postForm" method="post" action="/Uploads/Process.php" style="text-align:left; margin:0 auto; max-width:700px;">
            <input type="hidden" id="postId" name="postId" value="">
            <div class="form-group" style="margin-bottom:15px;">
                <label for="postTitle" style="display:block; font-weight:bold; margin-bottom:5px;">Tiêu đề</label>
                <input type="text" id="postTitle" name="postTitle" class="form-control" placeholder="Nhập tiêu đề bài viết" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label for="postDescription" style="display:block; font-weight:bold; margin-bottom:5px;">Mô tả</label>
                <textarea id="postDescription" name="postDescription" class="form-control" placeholder="Nhập mô tả bài viết" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; min-height:150px;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label for="postCategory" style="display:block; font-weight:bold; margin-bottom:5px;">Ghim bài</label>
                <select id="postCategory" name="postCategory" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="0" selected>Không ghim</option>
                    <option value="1">Ghim bài</option>
                </select>
            </div>
            <div style="text-align:center; margin-top:20px;">
                <button class="btn-submit" type="submit" id="submitButton">Đăng Bài</button>
                <button class="btn-preview" type="button" id="previewButton">Xem Trước</button>
            </div>
        </form>
        <div id="previewContainer" style="display:none; margin-top:20px; border:1px solid #ddd; padding:15px; text-align:left;">
            <h3 style="margin-bottom:10px;">Xem Trước Bài Viết</h3>
            <h4 id="previewTitle" style="margin-bottom:10px;"></h4>
            <div id="previewContent"></div>
        </div>
    </section>
    <section class="post-list" style="margin:40px auto 20px; text-align:center;">
        <h2 class="title" style="margin-bottom:10px; font-size:22px;">Danh Sách Bài Viết</h2>
        <hr style="border-top:1px solid #ccc; margin-bottom:20px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Tiêu đề</th>
                    <th>Mô tả</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="postTableBody">
                <!-- Bài viết sẽ được hiển thị tại đây -->
            </tbody>
        </table>
    </section>
</div>
<?php include '../Controllers/Footer.php'; ?>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.21.0/full/ckeditor.js"></script>
<script>
    const API_KEY = "NguyenDucKien-ImS";

    // Cấu hình CKEditor
    CKEDITOR.replace('postDescription', {
        toolbar: [
            { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'Undo', 'Redo'] },
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv'] },
            { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
            { name: 'colors', items: ['TextColor', 'BGColor'] },
            { name: 'links', items: ['Link', 'Unlink'] },
            { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar'] },
            { name: 'tools', items: ['Maximize', 'ShowBlocks'] },
            { name: 'document', items: ['Preview'] },
            { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll'] }
        ],
        removePlugins: 'elementspath,exportpdf',
        resize_enabled: true,
        height: 400,
        filebrowserUploadUrl: null,
        filebrowserImageUploadUrl: null,
        on: {
            instanceReady: function(evt) {
                // Tích hợp hàm xử lý imageBase64
                ImageBase64(evt.editor);
            }
        }
    });

    // Sự kiện submit form
    document.getElementById('postForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const postId = document.getElementById('postId').value;
        const postTitle = document.getElementById('postTitle').value.trim();
        const postDescription = CKEDITOR.instances.postDescription.getData().trim();
        const postCategory = document.getElementById('postCategory').value;

        if (!postTitle || !postDescription) {
            alert('Tiêu đề và mô tả không được để trống.');
            return;
        }

        const formData = new FormData();
        formData.append('postTitle', postTitle);
        formData.append('postDescription', postDescription);
        formData.append('postCategory', postCategory);

        // Nếu có postId -> cập nhật bài viết, ngược lại -> đăng bài mới
        let url = `/Api/Post?key=${API_KEY}`;
        let method = 'POST';
        if (postId) {
            url += `&id=${postId}`;
            method = 'PUT'; // hoặc nếu backend không hỗ trợ PUT thì bạn có thể dùng POST và phân biệt bằng tham số
        }

        fetch(url, {
            method: method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                resetForm();
                loadPosts();
                // Đổi lại tên nút sau khi cập nhật thành "Đăng Bài"
                document.getElementById('submitButton').innerText = 'Đăng Bài';
            } else {
                alert(data.message || 'Đã có lỗi xảy ra.');
            }
        })
        .catch(error => {
            alert('Không thể kết nối đến máy chủ.');
            console.error("Fetch error:", error);
        });
    });

    // Reset form
    function resetForm() {
        document.getElementById('postForm').reset();
        CKEDITOR.instances.postDescription.setData('');
        document.getElementById('previewContainer').style.display = 'none';
        document.getElementById('postId').value = '';
    }

    // Xem trước bài viết
    document.getElementById('previewButton').addEventListener('click', function() {
        const postTitle = document.getElementById('postTitle').value.trim();
        const postDescription = CKEDITOR.instances.postDescription.getData().trim();
        if (!postTitle && !postDescription) {
            alert('Tiêu đề và mô tả không được để trống.');
            return;
        }
        document.getElementById('previewTitle').innerText = postTitle || '(Không có tiêu đề)';
        document.getElementById('previewContent').innerHTML = postDescription || '(Không có nội dung)';
        document.getElementById('previewContainer').style.display = 'block';
    });

    // Tải danh sách bài viết
    function loadPosts() {
        fetch(`/Api/Post?key=${API_KEY}`)
            .then(response => response.json())
            .then(data => {
                const postTableBody = document.getElementById('postTableBody');
                postTableBody.innerHTML = '';
                data.posts.forEach(post => {
                    const shortDescription = post.description.length > 100 ? post.description.substring(0, 100) + '...' : post.description;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${post.title}</td>
                        <td>${shortDescription}</td>
                        <td>
                            <button class="btn-edit" onclick="editPost(${post.id})">Chỉnh Sửa</button>
                            <button class="btn-delete" onclick="deletePost(${post.id})">Xóa</button>
                        </td>`;
                    postTableBody.appendChild(row);
                });
            });
    }

    // Chỉnh sửa bài viết
    function editPost(id) {
        fetch(`/Api/Post?id=${id}&key=${API_KEY}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.post) {
                    document.getElementById('postId').value = id;
                    document.getElementById('postTitle').value = data.post.title;
                    CKEDITOR.instances.postDescription.setData(data.post.description);
                    document.getElementById('postCategory').value = data.post.category;
                    document.getElementById('submitButton').innerText = 'Lưu';
                } else {
                    alert(data.message || 'Không thể tải bài viết để chỉnh sửa.');
                }
            });
    }

    // Xóa bài viết
    function deletePost(id) {
        if (confirm('Bạn có chắc chắn muốn xóa bài viết này?')) {
            fetch(`/Api/Post?id=${id}&key=${API_KEY}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadPosts();
                } else {
                    alert(data.message || 'Lỗi.');
                }
            });
        }
    }

    // Xử lý upload ảnh dạng Base64 trong CKEditor
    function ImageBase64(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = loader => {
            return {
                upload: () => {
                    return loader.file.then(file => new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => {
                            const img = new Image();
                            img.src = reader.result;
                            img.onload = () => {
                                const canvas = document.createElement('canvas');
                                const maxWidth = 500, maxHeight = 300;
                                let width = img.width, height = img.height;
                                // Tính toán tỷ lệ để resize nếu vượt quá kích thước cho phép
                                if (width > maxWidth || height > maxHeight) {
                                    const ratio = width / height;
                                    if (width > maxWidth) {
                                        width = maxWidth;
                                        height = width / ratio;
                                    }
                                    if (height > maxHeight) {
                                        height = maxHeight;
                                        width = height * ratio;
                                    }
                                }
                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);
                                const resizedDataUrl = canvas.toDataURL(file.type);
                                // Trả về kết quả dưới dạng thẻ IMG (có thể điều chỉnh style nếu cần)
                                resolve({ default: `<img src="${resizedDataUrl}" style="max-width:100%; display:block; margin:0 auto;" />` });
                            };
                        };
                        reader.onerror = err => reject(err);
                        reader.readAsDataURL(file);
                    }));
                }
            };
        };
    }

    document.addEventListener('DOMContentLoaded', loadPosts);
</script>
<style>
    /* Bảng hiển thị bài viết */
    .table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 auto;
        text-align: left;
    }
    .table th, .table td {
        padding: 12px;
        border: 1px solid #ddd;
    }
    .table thead {
        background: #f5f5f5;
    }
    /* Nút */
    button {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s ease, transform 0.3s ease;
        margin: 2px;
    }
    .btn-submit {
        background: #337ab7;
        color: #fff;
    }
    .btn-submit:hover {
        background: #286090;
        transform: translateY(-2px);
    }
    .btn-preview {
        background: #5bc0de;
        color: #fff;
    }
    .btn-preview:hover {
        background: #31b0d5;
        transform: translateY(-2px);
    }
    .btn-edit {
        background: #5cb85c;
        color: #fff;
    }
    .btn-edit:hover {
        background: #449d44;
        transform: translateY(-2px);
    }
    .btn-delete {
        background: #d9534f;
        color: #fff;
    }
    .btn-delete:hover {
        background: #c9302c;
        transform: translateY(-2px);
    }
    /* Responsive cho mobile */
    @media (max-width: 768px) {
        .table td, .table th {
            padding: 8px;
        }
        button {
            width: 100%;
            margin: 4px 0;
        }
    }
</style>
