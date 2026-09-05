<?php
include '../Controllers/Header.php';

$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$post_id) {
    die('Invalid post ID');
}

$stmt = $Connect->prepare("SELECT id, title, description, created_at, comments FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die('Post not found');
}
$comments = json_decode($post['comments'], true);
if (!is_array($comments)) {
    $comments = [];
}
$comments = array_reverse($comments);
$commentsPerPage = 5;
$commentPage = filter_input(INPUT_GET, 'cp', FILTER_VALIDATE_INT) ?: 0;
$totalComments = count($comments);
$totalCommentPages = ceil($totalComments / $commentsPerPage);
$commentsToShow = array_slice($comments, $commentPage * $commentsPerPage, $commentsPerPage);
?>
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

            <div class="box_list_parent_next">
                <table cellpadding="0" cellspacing="0" width="99%" border="0" style="table-layout:fixed;word-wrap: break-word;">
                    <tbody>
                        <tr>
                            <td width="50px" align="center" class="box_list_c_s">
                                <img class="avatar" src="/images/avatar/6101.gif" alt="User">
                                <div class="box_list_b_s" style="background-color: #FFAF4D;">
                                    <div class="box_list_ads">
                                        <div class="box_oxx_admin">
                                            <a href="javascript:void(0)" style="font-size: 8px; text-decoration: none;">Admin</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="box_list_b_s">
                                <div class="box_list_ads">
                                    <div class="box_oxx_member">
                                        <span style="font-weight:normal;color:black;font-size:9px;">
                                            <i><?= timeAgo($post['created_at']) ?></i>
                                        </span>
                                        <span style="font-weight:normal;color:black;font-size:9px;float:right;">
                                            <i>#0</i>
                                        </span>
                                    </div>
                                    <div class="box_title_bviet"><?= htmlspecialchars($post['title']) ?></div>
                                    <div class="box_ndung_bviet"><?= nl2br(html_entity_decode($post['description'])) ?></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p></p>
                <center>
                    <a href="https://ngocrongtuoitho.com/" target="_blank">
                        <img src="/images/gif/new.gif"> Ngọc Rồng Tuổi Thơ <img src="/images/gif/new.gif">
                    </a>
                </center>
                <p></p>
                <?php
                $counter = $totalComments - ($commentPage * $commentsPerPage);
                foreach ($commentsToShow as $comment): ?>
                    <table cellpadding="0" cellspacing="0" width="99%" border="0" style="table-layout:fixed;word-wrap: break-word;">
                        <tbody>
                            <tr>
                                <td width="50px" align="center" class="box_list_c">
                                    <?php
                                    if ($comment['gender'] == 1) {
                                        $imgUrl = '/images/avatar/namec.png';
                                    } elseif ($comment['gender'] == 0) {
                                        $imgUrl = '/images/avatar/traidat.png';
                                    } elseif ($comment['gender'] == 2) {
                                        $imgUrl = '/images/avatar/xayda.png';
                                    } else {
                                        $imgUrl = '/images/avatar/6101.gif';
                                    }
                                    ?>
                                    <img class="avatar" src="<?= $imgUrl ?>" alt="User" style="width: 40px; height: 40px; border-radius: 50%;">
                                    <?= htmlspecialchars($comment['name']) ?>
                                </td>

                                <td class="box_list_b">
                                    <div class="box_list_ads">
                                        <div class="box_oxx_member">
                                            <span style="font-weight:normal;color:black;font-size:9px;">
                                                <?= timeAgo($comment['created_at']) ?>
                                            </span>
                                            <span style="font-weight:normal;color:black;font-size:9px;float:right;">
                                                <i>#<?= $counter ?></i>
                                            </span>
                                        </div>
                                        <div class="box_ndung_bviet"><?= nl2br(htmlspecialchars($comment['content'])) ?></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php
                    $counter++;
                endforeach;
                ?>
            </div>

            <div style="width: 100%; color: #FFFFFF; float: left; margin:0px;text-align:right; margin-bottom:10px;">
                <div class="box_phantrang">
                    <div class="pagination">
                        <?php if ($totalCommentPages > 1): ?>
                            <br>
                            <?php if ($commentPage > 0): ?>
                                <a href="?id=<?= $post_id ?>&cp=<?= $commentPage - 1 ?>" style="padding:6px 12px; border:1px solid #b65d2f; text-decoration:none; margin-right:4px; color:#fff; background:#b65d2f; border-radius:4px;">&laquo;</a>
                            <?php endif;
                            $range = 2;
                            for ($i = max(0, $commentPage - $range); $i < min($totalCommentPages, $commentPage + $range + 1); $i++):
                            ?>
                                <?php if ($i == $commentPage): ?>
                                    <span style="padding:6px 12px; border:1px solid #b65d2f; background:#b65d2f; color:#fff; margin-right:4px; border-radius:4px;"><?= $i + 1 ?></span>
                                <?php else: ?>
                                    <a href="?id=<?= $post_id ?>&cp=<?= $i ?>" style="padding:6px 12px; border:1px solid #b65d2f; text-decoration:none; margin-right:4px; color:#b65d2f; background:#fff; border-radius:4px;"><?= $i + 1 ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($commentPage < $totalCommentPages - 1): ?>
                                <a href="?id=<?= $post_id ?>&cp=<?= $commentPage + 1 ?>" style="padding:6px 12px; border:1px solid #b65d2f; text-decoration:none; color:#fff; background:#b65d2f; border-radius:4px;">&raquo;</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php if ($Login): ?>
            <div class="box_comment_new">
                <div id="comment_error" style="color:red; font-size:12px; margin-bottom:5px;"></div>
                <form id="comment_form">
                    <div style="display: flex; align-items: center; gap: 5px; background: #f9f9f9; border-radius: 8px; padding: 5px; position: relative;">
                        <button type="button" id="emoji_button" style="border: none; background: transparent; cursor: pointer; font-size: 20px;">😊</button>
                        <textarea id="comment_content" name="comment_content" rows="1" required placeholder="Nhập bình luận..."
                            style="flex: 1; border: none; outline: none; padding: 8px; border-radius: 8px; resize: none; font-size: 14px; background: transparent;"></textarea>
                        <button type="submit" style="border: none; background: #FF5722; color: white; padding: 8px 12px; border-radius: 6px; cursor: pointer;">Gửi</button>
                    </div>
                    <div id="emoji_picker" class="emoji-picker"></div>
                    <input type="hidden" name="post_id" id="post_id" value="<?= $post_id ?>">
                </form>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/emojione/4.5.0/lib/js/emojione.min.js"></script>
            <style>
                .emoji-picker {
                    display: none;
                    position: absolute;
                    bottom: 50px;
                    left: 5px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 5px;
                    max-width: 300px;
                    max-height: 200px;
                    overflow: auto;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                    z-index: 1000;
                }

                .emoji-picker span {
                    font-size: 20px;
                    cursor: pointer;
                    margin: 5px;
                    display: inline-block;
                }
            </style>
            <script>
                const emojiShortcodes = [
                    ':smile:', ':laughing:', ':blush:', ':smiley:', ':heart_eyes:',
                    ':sweat_smile:', ':joy:', ':wink:', ':thumbsup:', ':sob:',
                    ':angry:', ':clap:', ':grin:', ':sleeping:', ':stuck_out_tongue:',
                    ':sunglasses:', ':neutral_face:', ':thinking:', ':expressionless:',
                    ':unamused:', ':scream:', ':confused:', ':smirk:', ':flushed:',
                    ':relieved:', ':kissing_heart:', ':kissing:', ':yum:', ':satisfied:',
                    ':disappointed:', ':worried:', ':cry:', ':astonished:', ':mask:',
                    ':star_struck:', ':pleading_face:', ':rolling_on_the_floor_laughing:',
                    ':heart:', ':broken_heart:', ':two_hearts:', ':sparkling_heart:',
                    ':revolving_hearts:', ':cupid:'
                ];
                const emojiPicker = document.getElementById('emoji_picker');
                emojiShortcodes.forEach(code => {
                    const span = document.createElement('span');
                    span.innerHTML = emojione.shortnameToUnicode(code);
                    span.setAttribute('data-emoji', emojione.shortnameToUnicode(code));
                    emojiPicker.appendChild(span);
                });
                const emojiButton = document.getElementById('emoji_button');
                emojiButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    emojiPicker.style.display = (emojiPicker.style.display === 'none' || emojiPicker.style.display === '') ? 'block' : 'none';
                });
                emojiPicker.addEventListener('click', function(e) {
                    if (e.target && e.target.getAttribute('data-emoji')) {
                        const emoji = e.target.getAttribute('data-emoji');
                        document.getElementById('comment_content').value += emoji;
                    }
                });
                document.addEventListener('click', function(e) {
                    if (!emojiPicker.contains(e.target) && e.target.id !== 'emoji_button') {
                        emojiPicker.style.display = 'none';
                    }
                });

                const commentError = document.getElementById('comment_error');
                let lastCommentTime = 0;

                document.getElementById('comment_form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const textarea = document.getElementById('comment_content');
                    const postId = document.getElementById('post_id').value;
                    const commentContent = textarea.value.trim();
                    if (!commentContent) return;
                    fetch('/Api/Forum', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=addComment&post_id=${postId}&comment_content=${encodeURIComponent(commentContent)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            commentError.textContent = data.message;
                            if (data.status === 'success') {
                                textarea.value = '';
                                lastCommentTime = Date.now();
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            }
                        })
                        .catch(error => {
                            console.error('Lỗi:', error);
                            commentError.textContent = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
                        });
                });
            </script>
        <?php endif; ?>
    </div>
    <div class="clearfix"></div>
</div>

<?php include '../Controllers/Footer.php'; ?>