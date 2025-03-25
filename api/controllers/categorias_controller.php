<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Obter o método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Criar conexão com o banco de dados
$conn = new mysqli($host, $username, $password, $database);

// Verificar conexão
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Conexão falhou: ' . $conn->connect_error]));
}

// Função para sanitizar input
function sanitize($data) {
    return htmlspecialchars(strip_tags($data));
}

switch ($method) {
    case 'GET':
        // Listar todas as categorias
        $sql = "SELECT * FROM categorias ORDER BY nome";
        $result = $conn->query($sql);
        
        if ($result) {
            $categorias = [];
            while ($row = $result->fetch_assoc()) {
                $categorias[] = $row;
            }
            echo json_encode($categorias);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar categorias']);
        }
        break;

    case 'POST':
        // Adicionar nova categoria
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['nome']) || empty($data['nome']) || !isset($data['tipo'])) {
            echo json_encode(['success' => false, 'message' => 'Nome e tipo da categoria são obrigatórios']);
            break;
        }

        $nome = sanitize($data['nome']);
        $tipo = sanitize($data['tipo']);
        $cor = isset($data['cor']) ? sanitize($data['cor']) : '#000000';

        // Validar tipo
        if (!in_array($tipo, ['receita', 'despesa'])) {
            echo json_encode(['success' => false, 'message' => 'Tipo deve ser receita ou despesa']);
            break;
        }

        // Validar cor (formato hexadecimal)
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $cor)) {
            echo json_encode(['success' => false, 'message' => 'Cor inválida. Use formato hexadecimal (ex: #000000)']);
            break;
        }

        $sql = "INSERT INTO categorias (nome, tipo, cor) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nome, $tipo, $cor);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Categoria adicionada com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar categoria']);
        }
        break;

    case 'PUT':
        // Atualizar categoria existente
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id']) || !isset($data['nome']) || empty($data['nome']) || !isset($data['tipo'])) {
            echo json_encode(['success' => false, 'message' => 'ID, nome e tipo da categoria são obrigatórios']);
            break;
        }

        $id = (int)$data['id'];
        $nome = sanitize($data['nome']);
        $tipo = sanitize($data['tipo']);
        $cor = isset($data['cor']) ? sanitize($data['cor']) : '#000000';

        // Validar tipo
        if (!in_array($tipo, ['receita', 'despesa'])) {
            echo json_encode(['success' => false, 'message' => 'Tipo deve ser receita ou despesa']);
            break;
        }

        // Validar cor (formato hexadecimal)
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $cor)) {
            echo json_encode(['success' => false, 'message' => 'Cor inválida. Use formato hexadecimal (ex: #000000)']);
            break;
        }

        $sql = "UPDATE categorias SET nome = ?, tipo = ?, cor = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nome, $tipo, $cor, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar categoria']);
        }
        break;

    case 'DELETE':
        // Excluir categoria
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID da categoria inválido']);
            break;
        }

        // Verificar se a categoria está sendo usada em transações
        $sql = "SELECT COUNT(*) as count FROM transacoes WHERE categoria_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Não é possível excluir esta categoria pois está sendo usada em transações']);
            break;
        }

        // Excluir a categoria
        $sql = "DELETE FROM categorias WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir categoria']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Método não suportado']);
        break;
}

$conn->close(); 