-- kurulum/duello_temizle.sql
-- Ne işe yarar: Bütün düello geçmişini siler (test sonrası temizlik).
-- DİKKAT: Geri alınamaz. Çalıştırmadan önce yedek alın.
-- [2026-11] api/duello/ içindeki 'duello temizle veri tabanından.txt' notundan taşındı.

TRUNCATE TABLE `quiz_duellolar`;
