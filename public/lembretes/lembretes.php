<?php
session_start();
require 'db.php';

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_lembrete = $_POST['data_lembrete'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    if (empty($titulo) || empty($data_lembrete) || empty($tipo)) {
        $mensagem = '<div class="alert alert-danger">Preencha todos os campos obrigatórios.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO lembretes (
                    usuario_id, titulo, descricao, data_lembrete, tipo
                )
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$user_id, $titulo, $descricao, $data_lembrete, $tipo])) {
                // Registrar log de atividade
                $stmt = $pdo->prepare("
                    INSERT INTO logs_atividade (usuario_id, acao, detalhes, ip_address)
                    VALUES (?, 'cadastro_lembrete', ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    "Novo lembrete cadastrado: $titulo",
                    $_SERVER['REMOTE_ADDR']
                ]);

                // Criar notificação
                $stmt = $pdo->prepare("
                    INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo)
                    VALUES (?, ?, ?, 'info')
                ");
                $stmt->execute([
                    $user_id,
                    "Novo Lembrete",
                    "Um novo lembrete foi cadastrado: $titulo"
                ]);

                $pdo->commit();
                $mensagem = '<div class="alert alert-success">Lembrete cadastrado com sucesso!</div>';
            } else {
                throw new Exception("Erro ao cadastrar lembrete");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<div class="alert alert-danger">Erro ao cadastrar lembrete: ' . $e->getMessage() . '</div>';
        }
    }
}

// Buscar lembretes do usuário
$stmt = $pdo->prepare("
    SELECT * FROM lembretes 
    WHERE usuario_id = ? 
    ORDER BY data_lembrete ASC
");
$stmt->execute([$user_id]);
$lembretes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Lembretes</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Gerenciamento de Lembretes</h2>
        
        <?php echo $mensagem; ?>
        
        <!-- Botão para adicionar novo lembrete -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAddLembrete">
            <i class="fas fa-plus"></i> Novo Lembrete
        </button>

        <!-- Lista de lembretes -->
        <div class="row">
            <?php foreach ($lembretes as $lembrete): ?>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($lembrete['titulo']); ?></h5>
                        <span class="badge bg-<?php echo $lembrete['status'] === 'pendente' ? 'warning' : ($lembrete['status'] === 'concluido' ? 'success' : 'danger'); ?>">
                            <?php echo ucfirst($lembrete['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($lembrete['descricao'] ?? '')); ?></p>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('d/m/Y H:i', strtotime($lembrete['data_lembrete'])); ?>
                            </small>
                        </p>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="fas fa-tag"></i> 
                                <?php echo ucfirst(str_replace('_', ' ', $lembrete['tipo'])); ?>
                            </small>
                        </p>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-success" onclick="marcarConcluido(<?php echo $lembrete['id']; ?>)">
                            <i class="fas fa-check"></i> Concluir
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editarLembrete(<?php echo $lembrete['id']; ?>)">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="excluirLembrete(<?php echo $lembrete['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Adicionar Lembrete -->
    <div class="modal fade" id="modalAddLembrete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Lembrete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddLembrete" method="POST">
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="data_lembrete" class="form-label">Data e Hora</label>
                            <input type="datetime-local" class="form-control" id="data_lembrete" name="data_lembrete" required>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="conta_pagar">Conta a Pagar</option>
                                <option value="conta_receber">Conta a Receber</option>
                                <option value="meta">Meta</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html> 