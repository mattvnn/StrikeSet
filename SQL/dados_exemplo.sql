SET NAMES utf8mb4;

INSERT INTO treinos (dia, titulo, hora_inicio, hora_fim, descricao, nivel) VALUES
('SEG', 'Fundamentos',   '19:00', '21:00', 'Toque, manchete, saque e recepção.',       'iniciante'),
('QUA', 'Tático',        '19:30', '21:30', 'Sistemas de jogo, rodízio e cobertura.',    'intermediario'),
('SEX', 'Coletivo',      '18:00', '20:00', 'Jogo-treino com todo o time.',              'todos'),
('SAB', 'Preparação física', '09:00', '10:30', 'Força, salto e agilidade.',             'avancado');

INSERT INTO campeonatos (nome, tipo, status, local, data_inicio, data_fim, colocacao, icone, descricao) VALUES
('Campeonato IFSC',                'proximo',   'aberto',     'Ginásio IFSC Gaspar', '2027-03-13', '2027-03-14', NULL, NULL, NULL),
('Torneio Regional Universitário', 'proximo',   'confirmado', 'Arena Blumenau',      '2027-04-10', NULL,         NULL, NULL, NULL),
('Jogos da Integração SC',         'proximo',   'analise',    'Florianópolis',       '2027-05-22', '2027-05-24', NULL, NULL, NULL),
('Copa IFSC de Vôlei',             'disputado', 'finalizado', 'Ginásio IFSC Gaspar', '2026-06-14', '2026-06-15', '2º lugar', NULL, NULL),
('Torneio Integração IFSC',        'disputado', 'finalizado', NULL,                  '2025-10-04', NULL,         '1º lugar', NULL, NULL),
('Regional Universitário SC',      'disputado', 'finalizado', NULL,                  '2025-08-16', NULL,         '3º lugar', NULL, NULL),
('Copa Gaspar de Vôlei',           'disputado', 'finalizado', NULL,                  '2025-05-03', NULL,         '2º lugar', NULL, NULL),
('Torneio Amistoso Vale do Itajaí','disputado', 'finalizado', NULL,                  '2024-11-09', NULL,         '4º lugar', NULL, NULL),
('Vice-campeão regional',          'conquista', NULL, NULL, '2026-06-15', NULL, NULL, '🥈', 'Copa IFSC 2026'),
('Campeão integração IFSC',        'conquista', NULL, NULL, '2025-10-04', NULL, NULL, '🥇', 'Torneio IFSC 2025'),
('Melhor defesa',                  'conquista', NULL, NULL, '2025-08-16', NULL, NULL, '🛡️', 'Regional SC 2025'),
('Destaque universitário',         'conquista', NULL, NULL, '2025-05-03', NULL, NULL, '🏐', 'Copa Gaspar 2025'),
('Melhor saque',                   'conquista', NULL, NULL, '2025-10-04', NULL, NULL, '💥', 'Torneio Integração 2025'),
('Maior evolução',                 'conquista', NULL, NULL, '2024-12-01', NULL, NULL, '📈', 'Avaliação interna 2024');
