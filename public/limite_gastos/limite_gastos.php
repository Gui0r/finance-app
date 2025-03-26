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
    $limite_gastos = str_replace(',', '.', $_POST['limite_gastos'] ?? '');
    
    if (empty($limite_gastos)) {
        $mensagem = '<div class="alert alert-danger">Preencha o limite de gastos.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET limite_gastos = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$limite_gastos, $user_id])) {
                // Registrar log de atividade
                $stmt = $pdo->prepare("
                    INSERT INTO logs_atividade (usuario_id, acao, detalhes, ip_address)
                    VALUES (?, 'atualizar_limite_gastos', ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    "Limite de gastos atualizado para R$ $limite_gastos",
                    $_SERVER['REMOTE_ADDR']
                ]);

                // Criar notificação
                $stmt = $pdo->prepare("
                    INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo)
                    VALUES (?, ?, ?, 'info')
                ");
                $stmt->execute([
                    $user_id,
                    "Limite de Gastos Atualizado",
                    "Seu limite de gastos foi atualizado para R$ $limite_gastos"
                ]);

                $pdo->commit();
                $mensagem = '<div class="alert alert-success">Limite de gastos atualizado com sucesso!</div>';
            } else {
                throw new Exception("Erro ao atualizar limite de gastos");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<div class="alert alert-danger">Erro ao atualizar limite de gastos: ' . $e->getMessage() . '</div>';
        }
    }
}

// Buscar limite de gastos atual
$stmt = $pdo->prepare("SELECT limite_gastos FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$limite_atual = $usuario['limite_gastos'] ?? 0;

// Buscar gastos do mês atual
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total_gastos
    FROM transacoes
    WHERE usuario_id = ? 
    AND tipo = 'despesa'
    AND MONTH(data) = MONTH(CURRENT_DATE())
    AND YEAR(data) = YEAR(CURRENT_DATE())
");
$stmt->execute([$user_id]);
$gastos_mes = $stmt->fetch(PDO::FETCH_ASSOC);
$total_gastos = $gastos_mes['total_gastos'];

// Calcular percentual gasto
$percentual_gasto = $limite_atual > 0 ? ($total_gastos / $limite_atual) * 100 : 0;

// Buscar planejamento orçamentário do mês atual
$stmt = $pdo->prepare("
    SELECT c.nome, c.cor, po.valor_planejado, po.valor_realizado
    FROM planejamento_orcamento po
    JOIN categorias c ON po.categoria_id = c.id
    WHERE po.usuario_id = ?
    AND po.mes = MONTH(CURRENT_DATE())
    AND po.ano = YEAR(CURRENT_DATE())
    AND c.tipo = 'despesa'
    ORDER BY c.nome
");
$stmt->execute([$user_id]);
$planejamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limite de Gastos</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Limite de Gastos</h2>
        
        <?php echo $mensagem; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Configuração do Limite</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="limite_gastos" class="form-label">Limite de Gastos Mensal (R$)</label>
                                <input type="text" class="form-control" id="limite_gastos" name="limite_gastos" 
                                       value="<?php echo number_format($limite_atual, 2, ',', '.'); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Atualizar Limite</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumo do Mês</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar <?php echo $percentual_gasto > 100 ? 'bg-danger' : ($percentual_gasto > 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo min($percentual_gasto, 100); ?>%"
                                 aria-valuenow="<?php echo $percentual_gasto; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo number_format($percentual_gasto, 1); ?>%
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-0">Total Gastos:</p>
                                <h4 class="text-danger">R$ <?php echo number_format($total_gastos, 2, ',', '.'); ?></h4>
                            </div>
                            <div class="col-6">
                                <p class="mb-0">Limite:</p>
                                <h4>R$ <?php echo number_format($limite_atual, 2, ',', '.'); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Planejamento Orçamentário</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($planejamento)): ?>
                            <p class="text-muted">Nenhum planejamento orçamentário cadastrado para este mês.</p>
                        <?php else: ?>
                            <?php foreach ($planejamento as $item): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span style="color: <?php echo $item['cor']; ?>">
                                            <i class="fas fa-circle"></i> <?php echo htmlspecialchars($item['nome']); ?>
                                        </span>
                                        <span>
                                            R$ <?php echo number_format($item['valor_realizado'], 2, ',', '.'); ?> / 
                                            R$ <?php echo number_format($item['valor_planejado'], 2, ',', '.'); ?>
                                        </span>
                                    </div>
                                    <?php 
                                    $percentual = $item['valor_planejado'] > 0 
                                        ? ($item['valor_realizado'] / $item['valor_planejado']) * 100 
                                        : 0;
                                    ?>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" 
                                             role="progressbar" 
                                             style="width: <?php echo min($percentual, 100); ?>%; background-color: <?php echo $item['cor']; ?>"
                                             aria-valuenow="<?php echo $percentual; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>
