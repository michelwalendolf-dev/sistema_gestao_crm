function logout() {
    Swal.fire({
        title: "Sair do Sistema",
        text: "Confirma logout do sistema?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#e74c3c",
        cancelButtonColor: "#3498db",
        confirmButtonText: "Sim",
        cancelButtonText: "Não",
        backdrop: "transparent"
    }).then(result => {
        if (result.isConfirmed) {
            fetch("../actions/logout.php").then(() => {
                window.location.href = "../views/login.html";
            });
        }
    });
}

function openMenu() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    if (sidebar) sidebar.style.left = "60px";
    if (overlay) overlay.style.display = "block";
}

function closeMenu() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    if (sidebar) sidebar.style.left = "-250px";
    if (overlay) overlay.style.display = "none";
}

document.querySelectorAll("#sidebar a").forEach(link => {
    link.addEventListener("click", closeMenu);
});

function mostrarSenha(id = "senha") {
    const campo = document.getElementById(id);
    if (campo) campo.type = "text";
}

function esconderSenha(id = "senha") {
    const campo = document.getElementById(id);
    if (campo) campo.type = "password";
}

document.addEventListener("DOMContentLoaded", function () {
    const settingsIcon = document.querySelector(".settings-icon");
    const settingsMenu = document.getElementById("settingsMenu");

    if (settingsIcon && settingsMenu) {
        settingsIcon.addEventListener("click", function (e) {
            e.stopPropagation();
            settingsMenu.classList.toggle("active");
            settingsIcon.classList.toggle("rotate");
        });

        document.addEventListener("click", function (e) {
            if (!settingsMenu.contains(e.target)) {
                settingsMenu.classList.remove("active");
                settingsIcon.classList.remove("rotate");
            }
        });

        document.querySelectorAll(".menu-icon").forEach(icon => {
            icon.addEventListener("click", () => {
                settingsMenu.classList.remove("active");
                settingsIcon.classList.remove("rotate");
            });
        });
    }

    const formLogin = document.getElementById("formLogin");
    if (formLogin) {
        formLogin.addEventListener("submit", async function (e) {
            e.preventDefault();
            const resposta = await fetch("../actions/login.php", {
                method: "POST",
                body: new FormData(formLogin)
            });
            const resultado = await resposta.json();

            if (resultado.sucesso) {
                window.location.href = "../views/sistema.html";
                return;
            }

            Swal.fire({
                icon: "error",
                title: resultado.titulo,
                html: resultado.mensagem,
                confirmButtonText: "Ok",
                scrollbarPadding: false,
                confirmButtonColor: "#2d7dff"
            });
        });
    }

    const formRecuperar = document.getElementById("formRecuperar");
    if (formRecuperar) {
        formRecuperar.addEventListener("submit", async function (e) {
            e.preventDefault();
            const req = await fetch("../actions/enviar_codigo.php", {
                method: "POST",
                body: new FormData(formRecuperar)
            });
            const res = await req.json();

            if (res.sucesso) {
                Swal.fire({
                    icon: "success",
                    title: "Código enviado",
                    text: "Verifique seu e-mail",
                    confirmButtonColor: "#2d7dff"
                }).then(() => {
                    window.location.href = "../views/verificar_codigo.html";
                });
            } else {
                Swal.fire({ icon: "error", title: "Erro", text: res.mensagem });
            }
        });
    }

    const formVerificar = document.getElementById("formVerificar");
    const emailUsuarioEl = document.getElementById("emailUsuario");
    let emailUsuarioGlobal = "";

    if (formVerificar) {
        fetch("../actions/verificar_codigo.php")
            .then(res => res.json())
            .then(data => {
                if (emailUsuarioEl && data.email) {
                    emailUsuarioEl.innerText = data.email;
                    emailUsuarioGlobal = data.email;
                }
            })
            .catch(err => console.error("Erro ao buscar email:", err));

        formVerificar.addEventListener("submit", async function (e) {
            e.preventDefault();
            const req = await fetch("../actions/verificar_codigo.php", {
                method: "POST",
                body: new FormData(formVerificar)
            });
            const res = await req.json();

            if (res.sucesso) {
                window.location.href = "../views/nova_senha.html";
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Código inválido",
                    text: "O código informado está incorreto ou expirou.",
                    confirmButtonColor: "#2d7dff"
                });
            }
        });

        let tempo = 35;
        const contador = document.getElementById("contador");
        const btnReenviar = document.getElementById("reenviarBtn");

        if (contador && btnReenviar) {
            const intervalo = setInterval(() => {
                tempo--;
                contador.innerText = `Reenviar código em ${tempo}s`;
                if (tempo <= 0) {
                    clearInterval(intervalo);
                    contador.style.display = "none";
                    btnReenviar.style.display = "inline";
                }
            }, 1000);

            btnReenviar.addEventListener("click", async function (e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append("email", emailUsuarioGlobal);

                const req = await fetch("../actions/enviar_codigo.php", {
                    method: "POST",
                    body: formData
                });
                const res = await req.json();

                if (res.sucesso) {
                    Swal.fire({
                        icon: "success",
                        title: "Novo código enviado",
                        text: "Verifique seu e-mail.",
                        confirmButtonColor: "#2d7dff"
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: res.mensagem,
                        confirmButtonColor: "#2d7dff"
                    });
                }
            });
        }
    }

    const formSenha = document.getElementById("senhaForm");
    if (formSenha) {
        formSenha.addEventListener("submit", async function (e) {
            e.preventDefault();
            const senha = document.getElementById("senha").value;
            const confirmar = document.getElementById("confirmar").value;

            if (senha !== confirmar) {
                Swal.fire({ icon: "error", title: "As senhas não coincidem" });
                return;
            }

            const req = await fetch("../actions/alterar_senha.php", {
                method: "POST",
                body: new FormData(formSenha)
            });
            const res = await req.json();

            if (res.sucesso) {
                Swal.fire({
                    icon: "success",
                    title: "Senha alterada com sucesso",
                    confirmButtonColor: "#2d7dff"
                }).then(() => {
                    window.location.href = "../views/login.html";
                });
            }
        });
    }
});

function atualizarHora() {
    const agora = new Date();
    const h = agora.getHours().toString().padStart(2, "0");
    const m = agora.getMinutes().toString().padStart(2, "0");
    const s = agora.getSeconds().toString().padStart(2, "0");
    document.getElementById("hora").innerText = `${h}:${m}:${s}`;
}

setInterval(atualizarHora, 1000);

function abrirCaixa() {
    document.getElementById("telaCaixa").style.display = "flex";
}

function fecharCaixa() {
    document.getElementById("telaCaixa").style.display = "none";
}

function selecionarProduto(produto) {
    document.querySelectorAll(".produto").forEach(p => p.classList.remove("selecionado"));
    produto.classList.add("selecionado");

    const codigo = produto.querySelector(".linha1 span:first-child").innerText;
    const descricao = produto.querySelector(".linha1 span:last-child").innerText;
    const preco = produto.querySelector(".linha2 span:last-child").innerText;

    document.getElementById("descricaoProduto").innerText = descricao;
    document.getElementById("valorUnitario").innerText = `R$ ${preco}`;
    document.getElementById("resumoQtd").innerText = `1 X R$ ${preco}`;
    document.getElementById("resumoTotal").innerText = `R$ ${preco}`;
}

document.querySelectorAll(".produto").forEach(produto => {
    produto.addEventListener("click", function () {
        selecionarProduto(this);
    });
});

function adicionarProduto(codigo, descricao, preco) {
    const lista = document.querySelector(".pdv-lista");
    const item = document.createElement("div");
    item.className = "produto";
    item.innerHTML = `
    <div class="linha1">
        <span>${codigo}</span>
        <span>${descricao}</span>
    </div>
    <div class="linha2">
        <span>1 X UNID</span>
        <span>${preco}</span>
    </div>`;
    item.addEventListener("click", function () {
        selecionarProduto(item);
    });
    lista.appendChild(item);
}

function abrirModalProduto() {
    document.getElementById("pdvModal").style.display = "block";
    setTimeout(() => document.getElementById("codigoProdutoInput").focus(), 100);
}

function fecharModalProduto() {
    document.getElementById("pdvModal").style.display = "none";
    document.getElementById("codigoProdutoInput").value = "";
}

document.addEventListener("keydown", function (e) {
    if (e.key === "F8") {
        e.preventDefault();
        abrirModalProduto();
    }

    if (e.key === "Enter") {
        const modal = document.getElementById("pdvModal");
        if (modal && modal.style.display === "block") {
            prosseguirProduto();
        }
    }
});

function prosseguirProduto() {
    const codigo = document.getElementById("codigoProdutoInput").value;
    const continuar = document.getElementById("continuarAdd").checked;

    if (!codigo) return;

    adicionarProduto(codigo, `Produto código ${codigo}`, "9,99");

    if (!continuar) {
        fecharModalProduto();
    } else {
        const input = document.getElementById("codigoProdutoInput");
        input.value = "";
        input.focus();
    }
}

function removerProduto() {
    const selecionado = document.querySelector(".produto.selecionado");
    if (selecionado) selecionado.remove();
}

function multiploProduto() {
    const selecionado = document.querySelector(".produto.selecionado");
    if (!selecionado) return;

    const linhaQtd = selecionado.querySelector(".linha2 span:first-child");
    const qtd = parseInt(linhaQtd.innerText) + 1;
    linhaQtd.innerText = `${qtd} X UNID`;
}

const modal = document.getElementById("pdvModalBox");
const header = document.getElementById("pdvHeader");

let offsetX = 0, offsetY = 0, isDragging = false;

header.addEventListener("mousedown", function (e) {
    isDragging = true;
    offsetX = e.clientX - modal.offsetLeft;
    offsetY = e.clientY - modal.offsetTop;
});

document.addEventListener("mousemove", function (e) {
    if (!isDragging) return;
    modal.style.position = "fixed";
    modal.style.left = `${e.clientX - offsetX}px`;
    modal.style.top = `${e.clientY - offsetY}px`;
});

document.addEventListener("mouseup", function () {
    isDragging = false;
});
