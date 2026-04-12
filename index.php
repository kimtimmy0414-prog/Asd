<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

define('DATA_DIR', __DIR__ . '/data');
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);

$files = ['users.json','posts.json','comments.json','checkins.json','schedules.json'];
foreach ($files as $f) {
    $path = DATA_DIR . '/' . $f;
    if (!file_exists($path)) file_put_contents($path, '[]');
}

function json_read($filename) {
    $path = DATA_DIR . '/' . $filename;
    return json_decode(file_get_contents($path), true) ?: [];
}

function json_write($filename, $data) {
    $path = DATA_DIR . '/' . $filename;
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_admin() {
    $u = current_user();
    return $u && $u['username'] === 'has';
}

function add_point($user_id, $point, $reason) {
    $users = json_read('users.json');
    foreach ($users as &$u) {
        if ($u['id'] == $user_id) {
            $u['points'] += $point;
            if (!isset($u['point_history'])) $u['point_history'] = [];
            $u['point_history'][] = ['date'=>date('Y-m-d H:i'), 'point'=>$point, 'reason'=>$reason];
            break;
        }
    }
    json_write('users.json', $users);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'join') {
        $users = json_read('users.json');
        $new_id = empty($users) ? 1 : max(array_column($users,'id')) + 1;
        $users[] = ['id'=>$new_id, 'username'=>trim($_POST['username']), 'password'=>password_hash($_POST['password'], PASSWORD_DEFAULT), 'email'=>trim($_POST['email']), 'points'=>100, 'join_date'=>date('Y-m-d'), 'post_count'=>0, 'attend_count'=>0];
        json_write('users.json', $users);
        echo "<script>alert('회원가입 완료!'); location.href='index.php';</script>"; exit;
    }

    if ($action === 'login') {
        $users = json_read('users.json');
        foreach ($users as $u) {
            if ($u['username'] === $_POST['username'] && password_verify($_POST['password'], $u['password'])) {
                $_SESSION['user'] = $u;
                echo "<script>alert('로그인 성공!'); location.href='index.php';</script>"; exit;
            }
        }
        echo "<script>alert('아이디 또는 비밀번호가 틀립니다.\\n관리자: has / khs10937'); history.back();</script>"; exit;
    }

    if ($action === 'write_post' && current_user()) {
        $posts = json_read('posts.json');
        $new_id = empty($posts) ? 1 : max(array_column($posts,'id')) + 1;
        $posts[] = ['id'=>$new_id, 'title'=>trim($_POST['title']), 'content'=>trim($_POST['content']), 'author_id'=>current_user()['id'], 'author_name'=>current_user()['username'], 'date'=>date('Y-m-d H:i:s'), 'views'=>0];
        json_write('posts.json', $posts);

        $users = json_read('users.json');
        foreach ($users as &$u) { if ($u['id'] == current_user()['id']) { $u['post_count']++; break; } }
        json_write('users.json', $users);
        add_point(current_user()['id'], 10, '게시글 작성');
        echo "<script>alert('게시물이 등록되었습니다.'); location.href='index.php';</script>"; exit;
    }

    if ($action === 'add_comment' && current_user()) {
        $posts = json_read('posts.json');
        foreach ($posts as &$p) {
            if ($p['id'] == $_POST['post_id']) {
                if (!isset($p['comments'])) $p['comments'] = [];
                $p['comments'][] = ['author_id'=>current_user()['id'], 'author_name'=>current_user()['username'], 'content'=>trim($_POST['comment']), 'date'=>date('Y-m-d H:i:s')];
                break;
            }
        }
        json_write('posts.json', $posts);
        echo "<script>location.href='index.php';</script>"; exit;
    }

    if ($action === 'checkin' && current_user()) {
        $checkins = json_read('checkins.json');
        $today = date('Y-m-d');
        foreach ($checkins as $c) { if ($c['user_id'] == current_user()['id'] && $c['date'] == $today) { echo "<script>alert('오늘 이미 출석하셨습니다.'); location.href='index.php';</script>"; exit; } }
        $checkins[] = ['user_id'=>current_user()['id'], 'date'=>$today];
        json_write('checkins.json', $checkins);

        $users = json_read('users.json');
        foreach ($users as &$u) { if ($u['id'] == current_user()['id']) { $u['attend_count']++; break; } }
        json_write('users.json', $users);
        add_point(current_user()['id'], 50, '출석 체크');
        echo "<script>alert('출석 체크 완료! +50포인트'); location.href='index.php';</script>"; exit;
    }

    if (is_admin()) {
        if ($action === 'add_schedule') {
            $schedules = json_read('schedules.json');
            $schedules[] = ['date'=>$_POST['date'], 'time'=>$_POST['time'], 'opponent'=>$_POST['opponent'], 'is_home'=>$_POST['is_home']==='1', 'stadium'=>$_POST['stadium']];
            json_write('schedules.json', $schedules);
            echo "<script>alert('경기 일정이 추가되었습니다.'); location.href='index.php';</script>"; exit;
        }

        if ($action === 'give_point') {
            add_point($_POST['user_id'], (int)$_POST['point'], $_POST['reason'] ?: '관리자 지급');
            echo "<script>alert('포인트가 지급되었습니다.'); location.href='index.php';</script>"; exit;
        }
    }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $posts = json_read('posts.json');
    foreach ($posts as &$p) {
        if ($p['id'] == $_GET['view']) {
            $p['views'] = ($p['views'] ?? 0) + 1;
            break;
        }
    }
    json_write('posts.json', $posts);
    header("Location: index.php#post".$p['id']);
    exit;
}

$user = current_user();
$posts = json_read('posts.json');
$users = json_read('users.json');
$checkins = json_read('checkins.json');
$schedules = json_read('schedules.json');

$today = date('Y-m-d');
$today_check = false;
if ($user) foreach ($checkins as $c) { if ($c['user_id'] == $user['id'] && $c['date'] == $today) $today_check = true; }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="vLd_aFtMAvtBejGxksFzhPVvQHnaw-wkMCqhMKvivcY" />
    <title>롯데클럽 - We are Giants!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .top-header { background:#c8102e; color:white; padding:15px 0; }
        .logo-img { height:60px; }
        .nav-link { color:white !important; font-weight:bold; }
        .nav-link:hover { background:rgba(255,255,255,0.2); }
        .post-card { transition: all 0.2s; }
        .post-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<!-- 상단 헤더 (알 자이언츠 + 롯데 로고) -->
<div class="top-header">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/90/Lotte_Giants_logo_2018.png/800px-Lotte_Giants_logo_2018.png" alt="롯데 자이언츠" class="logo-img me-3">
                <h1 class="mb-0 fw-bold fs-2">롯데 자이언츠</h1>
            </div>
            <!-- 오른쪽 탭 메뉴 -->
            <ul class="nav nav-pills">
                <li class="nav-item"><a class="nav-link" href="#home">홈</a></li>
                <li class="nav-item"><a class="nav-link" href="#board">게시판</a></li>
                <li class="nav-item"><a class="nav-link" href="#ranking">랭킹</a></li>
                <li class="nav-item"><a class="nav-link" href="#schedule">경기일정</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="container mt-4" id="home">
    <div class="hero text-center py-5 bg-dark text-white rounded mb-4">
        <h1 class="display-4 fw-bold">We are Giants!</h1>
        <p class="lead">부산 갈매기와 함께하는 진짜 팬클럽</p>
    </div>

    <?php if (!$user): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5>로그인</h5>
            <form method="post" class="row g-2">
                <input type="hidden" name="action" value="login">
                <div class="col-auto"><input type="text" name="username" class="form-control" placeholder="아이디" required></div>
                <div class="col-auto"><input type="password" name="password" class="form-control" placeholder="비밀번호" required></div>
                <div class="col-auto"><button class="btn btn-danger">로그인</button></div>
            </form>
            <a href="#" data-bs-toggle="modal" data-bs-target="#joinModal" class="text-danger">회원가입</a>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div><?= htmlspecialchars($user['username']) ?>님 환영합니다! (포인트: <b><?= $user['points'] ?></b>P)</div>
        <div>
            <a href="?logout=1" class="btn btn-sm btn-outline-danger">로그아웃</a>
            <button onclick="checkin()" class="btn btn-sm btn-success ms-2" <?= $today_check ? 'disabled' : '' ?>>
                <?= $today_check ? '오늘 출석 완료' : '출석 체크 (+50P)' ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <div class="card mb-4" style="background:#fff3cd; border:2px solid #ffc107;">
        <div class="card-header bg-warning text-dark"><strong>👑 관리자 모드</strong></div>
        <div class="card-body">
            <h5>경기 일정 추가</h5>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="add_schedule">
                <div class="col-md-3"><input type="date" name="date" class="form-control" required></div>
                <div class="col-md-2"><input type="time" name="time" class="form-control" required></div>
                <div class="col-md-3"><input type="text" name="opponent" class="form-control" placeholder="상대팀" required></div>
                <div class="col-md-2">
                    <select name="is_home" class="form-select">
                        <option value="1">홈</option>
                        <option value="0">원정</option>
                    </select>
                </div>
                <div class="col-md-2"><input type="text" name="stadium" class="form-control" placeholder="구장" value="사직" required></div>
                <div class="col-12"><button class="btn btn-warning w-100">경기 일정 등록</button></div>
            </form>

            <hr>
            <h5>회원에게 포인트 지급</h5>
            <form method="post">
                <input type="hidden" name="action" value="give_point">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select name="user_id" class="form-select" required>
                            <option value="">회원 선택</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= $u['points'] ?>P)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" name="point" class="form-control" placeholder="포인트" required></div>
                    <div class="col-md-3"><input type="text" name="reason" class="form-control" placeholder="지급 사유"></div>
                    <div class="col-md-2"><button class="btn btn-danger w-100">지급</button></div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user): ?>
    <div class="card mb-4" id="board">
        <div class="card-header">새 게시물 작성</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="write_post">
                <input type="text" name="title" class="form-control mb-2" placeholder="제목" required>
                <textarea name="content" class="form-control mb-2" rows="4" placeholder="내용을 입력하세요" required></textarea>
                <button class="btn btn-danger">게시하기</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-3">최신 게시물</h4>
    <?php foreach (array_slice(array_reverse($posts), 0, 10) as $post): ?>
    <div class="card mb-3 post-card" id="post<?= $post['id'] ?>">
        <div class="card-body">
            <h5><a href="?view=<?= $post['id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($post['title']) ?></a></h5>
            <p class="text-muted small"><?= htmlspecialchars($post['author_name']) ?> · <?= $post['date'] ?> · 조회 <?= $post['views'] ?? 0 ?></p>
            <p><?= nl2br(htmlspecialchars(mb_substr($post['content'],0,200))) ?>...</p>
            
            <?php if (isset($post['comments']) && count($post['comments']) > 0): ?>
            <div class="small text-muted">댓글 <?= count($post['comments']) ?>개</div>
            <?php endif; ?>

            <?php if ($user): ?>
            <form method="post" class="mt-3 input-group">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <input type="text" name="comment" class="form-control" placeholder="댓글을 입력하세요" required>
                <button class="btn btn-outline-secondary">등록</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- 랭킹 & 경기일정 (탭으로 이동 가능) -->
<div class="container mt-5" id="ranking">
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">🏆 실시간 랭킹</div>
        <div class="card-body">
            <h6>포인트 랭킹</h6>
            <?php 
            $rank_users = $users;
            usort($rank_users, fn($a,$b) => $b['points'] <=> $a['points']);
            foreach (array_slice($rank_users, 0, 5) as $u): ?>
                <div><?= htmlspecialchars($u['username']) ?> — <?= $u['points'] ?>P</div>
            <?php endforeach; ?>

            <hr>
            <h6>출석 랭킹</h6>
            <?php 
            usort($rank_users, fn($a,$b) => $b['attend_count'] <=> $a['attend_count']);
            foreach (array_slice($rank_users, 0, 5) as $u): ?>
                <div><?= htmlspecialchars($u['username']) ?> (<?= $u['attend_count'] ?>회)</div>
            <?php endforeach; ?>

            <hr>
            <h6>게시글 수 랭킹</h6>
            <?php 
            usort($rank_users, fn($a,$b) => $b['post_count'] <=> $a['post_count']);
            foreach (array_slice($rank_users, 0, 5) as $u): ?>
                <div><?= htmlspecialchars($u['username']) ?> (<?= $u['post_count'] ?>개)</div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container mt-4" id="schedule">
    <div class="card">
        <div class="card-header">⚾ 2026 롯데 경기 일정</div>
        <div class="card-body">
            <?php if (empty($schedules)): ?>
                <p>등록된 경기 일정이 없습니다. (관리자만 추가 가능)</p>
            <?php else: 
                usort($schedules, fn($a,$b) => $a['date'] <=> $b['date']);
                foreach ($schedules as $s): ?>
                    <div class="mb-2"><?= $s['date'] ?> <?= $s['time'] ?> — <?= $s['is_home'] ? '사직 vs ' : '원정 @ ' ?><?= $s['opponent'] ?> (<?= $s['stadium'] ?>)</div>
                <?php endforeach; 
            endif; ?>
        </div>
    </div>
</div>

<!-- 회원가입 모달 -->
<div class="modal fade" id="joinModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>회원가입</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="join">
                    <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="아이디" required></div>
                    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="비밀번호" required></div>
                    <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="이메일" required></div>
                    <button class="btn btn-danger w-100">가입하기</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function checkin() {
    if (confirm('출석 체크하시겠습니까?')) {
        const f = document.createElement('form'); f.method='POST';
        f.innerHTML = '<input type="hidden" name="action" value="checkin">';
        document.body.appendChild(f); f.submit();
    }
}
</script>
</body>
</html>
