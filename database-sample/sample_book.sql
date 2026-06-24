USE shiorinote;

INSERT INTO books(title,author,publisher,cover_path,memo,progress_unit,created_by,created_at_utc,updated_at_utc)
VALUES('サンプル図書','サンプル作者','サンプル発行機関','','管理システムと進展ページの動作確認用サンプルです。','section',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6));
SET @book_id = LAST_INSERT_ID();
INSERT INTO book_toc(book_id,parent_id,level,sort_order,title,created_at_utc) VALUES(@book_id,NULL,1,10,'はじめに',UTC_TIMESTAMP(6));
SET @c1 = LAST_INSERT_ID();
INSERT INTO book_toc(book_id,parent_id,level,sort_order,title,created_at_utc) VALUES(@book_id,@c1,2,20,'読書の準備',UTC_TIMESTAMP(6)),(@book_id,@c1,2,30,'進展管理の考え方',UTC_TIMESTAMP(6));
INSERT INTO book_toc(book_id,parent_id,level,sort_order,title,created_at_utc) VALUES(@book_id,NULL,1,40,'実践',UTC_TIMESTAMP(6));
SET @c2 = LAST_INSERT_ID();
INSERT INTO book_toc(book_id,parent_id,level,sort_order,title,created_at_utc) VALUES(@book_id,@c2,2,50,'毎日の記録',UTC_TIMESTAMP(6)),(@book_id,@c2,2,60,'完結スタンプ',UTC_TIMESTAMP(6));
