// Carregar categorias ao iniciar a página
document.addEventListener('DOMContentLoaded', () => {
    carregarCategorias();
});

// Função para carregar todas as categorias
function carregarCategorias() {
    fetch('../api/controllers/categorias.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('tabelaCategorias');
            tbody.innerHTML = '';

            data.forEach(categoria => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${categoria.id}</td>
                    <td>${categoria.nome}</td>
                    <td>${categoria.tipo}</td>
                    <td>
                        <div class="color-preview" style="background-color: ${categoria.cor}"></div>
                        ${categoria.cor}
                    </td>
                    <td>
                        <button class="btn btn-link btn-action btn-edit" onclick="editarCategoria(${categoria.id}, '${categoria.nome}', '${categoria.tipo}', '${categoria.cor}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-link btn-action btn-delete" onclick="excluirCategoria(${categoria.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar categorias:', error);
            alert('Erro ao carregar categorias. Por favor, tente novamente.');
        });
}

// Função para salvar nova categoria
function salvarCategoria() {
    const nome = document.getElementById('nome').value;
    const tipo = document.getElementById('tipo').value;
    const cor = document.getElementById('cor').value;

    if (!nome.trim()) {
        alert('Por favor, preencha o nome da categoria.');
        return;
    }

    if (!tipo) {
        alert('Por favor, selecione o tipo da categoria.');
        return;
    }

    const dados = {
        nome: nome,
        tipo: tipo,
        cor: cor
    };

    fetch('../api/controllers/categorias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('formAddCategoria').reset();
            bootstrap.Modal.getInstance(document.getElementById('modalAddCategoria')).hide();
            carregarCategorias();
            alert('Categoria adicionada com sucesso!');
        } else {
            alert('Erro ao adicionar categoria: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar categoria:', error);
        alert('Erro ao salvar categoria. Por favor, tente novamente.');
    });
}

// Função para preparar edição de categoria
function editarCategoria(id, nome, tipo, cor) {
    document.getElementById('editId').value = id;
    document.getElementById('editNome').value = nome;
    document.getElementById('editTipo').value = tipo;
    document.getElementById('editCor').value = cor;
    
    new bootstrap.Modal(document.getElementById('modalEditCategoria')).show();
}

// Função para atualizar categoria
function atualizarCategoria() {
    const id = document.getElementById('editId').value;
    const nome = document.getElementById('editNome').value;
    const tipo = document.getElementById('editTipo').value;
    const cor = document.getElementById('editCor').value;

    if (!nome.trim()) {
        alert('Por favor, preencha o nome da categoria.');
        return;
    }

    if (!tipo) {
        alert('Por favor, selecione o tipo da categoria.');
        return;
    }

    const dados = {
        id: id,
        nome: nome,
        tipo: tipo,
        cor: cor
    };

    fetch('../api/controllers/categorias.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalEditCategoria')).hide();
            carregarCategorias();
            alert('Categoria atualizada com sucesso!');
        } else {
            alert('Erro ao atualizar categoria: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar categoria:', error);
        alert('Erro ao atualizar categoria. Por favor, tente novamente.');
    });
}

// Função para excluir categoria
function excluirCategoria(id) {
    if (!confirm('Tem certeza que deseja excluir esta categoria?')) {
        return;
    }

    fetch(`../api/controllers/categorias.php?id=${id}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarCategorias();
            alert('Categoria excluída com sucesso!');
        } else {
            alert('Erro ao excluir categoria: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao excluir categoria:', error);
        alert('Erro ao excluir categoria. Por favor, tente novamente.');
    });
} 