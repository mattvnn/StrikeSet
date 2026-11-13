-- =========================================================
-- StrikeSet Gaspar - criação das tabelas (banco NOVO, vazio)
--
-- Se o banco já existe com dados, NÃO rode este arquivo:
-- use o SQL/migrar_datas.php (veja o README no topo dele).
-- =========================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Usuários
-- ---------------------------------------------------------
CREATE TABLE usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Recuperação de senha ("Esqueci minha senha")
-- Guarda só o HASH do token; o token em si vai no link do e-mail.
-- ---------------------------------------------------------
CREATE TABLE recuperacao_senha (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_recuperacao_token (token_hash),
    KEY idx_recuperacao_usuario (usuario_id),

    CONSTRAINT fk_recuperacao_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Treinos (horário em TIME, não mais em texto)
-- ---------------------------------------------------------
CREATE TABLE treinos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dia ENUM('SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB') NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME DEFAULT NULL,
    descricao VARCHAR(500),
    nivel ENUM('iniciante', 'intermediario', 'avancado', 'todos') NOT NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Campeonatos (datas em DATE; o ano sai da própria data)
-- ---------------------------------------------------------
CREATE TABLE campeonatos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    tipo ENUM('proximo', 'disputado', 'conquista') NOT NULL,
    status ENUM('aberto', 'confirmado', 'analise', 'finalizado') DEFAULT NULL,
    local VARCHAR(200),
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    colocacao VARCHAR(30),
    icone VARCHAR(20),
    descricao TEXT,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_campeonatos_tipo (tipo),
    KEY idx_campeonatos_data (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Álbuns da galeria
-- ---------------------------------------------------------
CREATE TABLE albuns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    categoria ENUM('treinos', 'campeonatos', 'time', 'bastidores') NOT NULL,
    descricao VARCHAR(500),
    status ENUM('publicado', 'oculto') NOT NULL DEFAULT 'publicado',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_albuns_categoria (categoria),
    KEY idx_albuns_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Fotos
-- ---------------------------------------------------------
CREATE TABLE fotos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    album_id INT UNSIGNED NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamanho INT UNSIGNED,
    imagem MEDIUMBLOB NOT NULL,
    legenda VARCHAR(255),
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_fotos_album (album_id),

    CONSTRAINT fk_fotos_album
        FOREIGN KEY (album_id)
        REFERENCES albuns(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- Inscrições dos jogadores nos treinos
-- ---------------------------------------------------------
CREATE TABLE inscricoes_treino (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    treino_id INT UNSIGNED NOT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    status ENUM('pendente', 'confirmada', 'cancelada') NOT NULL DEFAULT 'pendente',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_treino (usuario_id, treino_id),
    KEY idx_inscricao_usuario (usuario_id),
    KEY idx_inscricao_treino (treino_id),

    CONSTRAINT fk_inscricao_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inscricao_treino
        FOREIGN KEY (treino_id)
        REFERENCES treinos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
