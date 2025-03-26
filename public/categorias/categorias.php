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
    $nome = $_POST['nome'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $cor = $_POST['cor'] ?? '#000000';
    $icone = $_POST['icone'] ?? null;
    $descricao = $_POST['descricao'] ?? null;

    if (empty($nome) || empty($tipo)) {
        $mensagem = '<div class="alert alert-danger">Preencha todos os campos obrigatórios.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO categorias (
                    usuario_id, nome, tipo, cor, icone, descricao
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$user_id, $nome, $tipo, $cor, $icone, $descricao])) {
                // Registrar log de atividade
                $stmt = $pdo->prepare("
                    INSERT INTO logs_atividade (usuario_id, acao, detalhes, ip_address)
                    VALUES (?, 'cadastro_categoria', ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    "Nova categoria cadastrada: $nome ($tipo)",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $pdo->commit();
                $mensagem = '<div class="alert alert-success">Categoria cadastrada com sucesso!</div>';
            } else {
                throw new Exception("Erro ao cadastrar categoria");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<div class="alert alert-danger">Erro ao cadastrar categoria: ' . $e->getMessage() . '</div>';
        }
    }
}

// Buscar categorias do usuário
$stmt = $pdo->prepare("
    SELECT * FROM categorias 
    WHERE usuario_id = ? AND ativo = 1
    ORDER BY tipo, nome
");
$stmt->execute([$user_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Categorias</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Gerenciamento de Categorias</h2>
        
        <?php echo $mensagem; ?>
        
        <!-- Botão para adicionar nova categoria -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAddCategoria">
            <i class="fas fa-plus"></i> Nova Categoria
        </button>

        <!-- Tabela de categorias -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Cor</th>
                        <th>Ícone</th>
                        <th>Descrição</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($categoria['nome']); ?></td>
                        <td><?php echo ucfirst($categoria['tipo']); ?></td>
                        <td>
                            <span class="color-preview" style="background-color: <?php echo $categoria['cor']; ?>"></span>
                        </td>
                        <td>
                            <?php if ($categoria['icone']): ?>
                                <i class="<?php echo htmlspecialchars($categoria['icone']); ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($categoria['descricao'] ?? ''); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarCategoria(<?php echo $categoria['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="excluirCategoria(<?php echo $categoria['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar Categoria -->
    <div class="modal fade" id="modalAddCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddCategoria" method="POST">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="receita">Receita</option>
                                <option value="despesa">Despesa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="cor" class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="cor" name="cor" value="#000000">
                        </div>
                        <div class="mb-3">
                            <label for="icone" class="form-label">Ícone (Font Awesome)</label>
                            <input type="text" class="form-control" id="icone" name="icone" placeholder="Ex: fas fa-home">
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
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