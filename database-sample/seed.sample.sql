USE shiorinote;

-- Sample initial developer account for local testing only.
-- Change this account immediately after installation, or replace these values before import.
-- Sample login ID: admin_08onpm4tlh
-- Sample email: admin+hvuv75f5@example.local
-- Sample password: 9coHvBPH4GtiJ_mUTO5@Xnbs

INSERT INTO users(
    uid, login_id, email, password_salt, password_hash, nickname, avatar_path, bio, link_url, timezone, role, status, created_at_utc, updated_at_utc
) VALUES (
    1,
    'admin_08onpm4tlh',
    'admin+hvuv75f5@example.local',
    UNHEX('ca2e043fddb3f4601576f4e731cf015d7e7112f315383bc94199879f1e17e933'),
    '$argon2id$v=19$m=65536,t=4,p=1$VXZaMnFuZzE2SVR6UnQ0dg$P6rbv8+HH6b2UhwKXeG13gdGoLTDs+I+QTi0zvPgqRg',
    'Sample Admin',
    '/upload/avatar/default-avatar.svg',
    'Sample developer account. Change or delete this account before production use.',
    '',
    'Asia/Tokyo',
    'developer',
    'active',
    UTC_TIMESTAMP(6),
    UTC_TIMESTAMP(6)
) ON DUPLICATE KEY UPDATE
    login_id = VALUES(login_id),
    email = VALUES(email),
    nickname = VALUES(nickname),
    avatar_path = VALUES(avatar_path),
    bio = VALUES(bio),
    link_url = VALUES(link_url),
    timezone = VALUES(timezone),
    role = VALUES(role),
    status = VALUES(status),
    updated_at_utc = UTC_TIMESTAMP(6);
