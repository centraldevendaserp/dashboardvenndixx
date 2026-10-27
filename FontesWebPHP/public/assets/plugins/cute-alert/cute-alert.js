// Alert box design by Igor Ferrão de Souza: https://www.linkedin.com/in/igor-ferr%C3%A3o-de-souza-4122407b/

const cuteAlert = ({
  type,
  title,
  message,
  buttonText = "OK",
  confirmText = "OK",
  cancelText = "Cancel",
  closeStyle,
}) => {
  return new Promise((resolve) => {
    const existingAlert = document.querySelector(".alert-wrapper");

    if (existingAlert) {
      existingAlert.remove();
    }

    const body = document.querySelector("body");

    const scripts = document.getElementsByTagName("script");

    let src = "";

    for (let script of scripts) {
      if (script.src.includes("cute-alert.js")) {
        src = script.src.substring(0, script.src.lastIndexOf("/"));
      }
    }

    let btnTemplate = `
    <button class="alert-button ${type}-bg ${type}-btn">${buttonText}</button>
    `;

    if (type === "question") {
      btnTemplate = `
      <div class="question-buttons">
        <button class="confirm-button ${type}-bg ${type}-btn">${confirmText}</button>
        <button class="cancel-button error-bg error-btn">${cancelText}</button>
      </div>
      `;
    }

    const template = `
    <div class="alert-wrapper">
      <div class="alert-frame">
        <div class="alert-header ${type}-bg">
          <span class="alert-close ${closeStyle === "circle" ? "alert-close-circle" : "alert-close-default"}">X</span>
          <img class="alert-img" src="${src}/img/${type}.svg" />
        </div>
        <div class="alert-body">
          <span class="alert-title"></span>
          <span class="alert-message"></span>
          ${btnTemplate}
        </div>
      </div>
    </div>
    `;

    body.insertAdjacentHTML("afterend", template);

    // `title` e `message` entram como TEXTO, nao como HTML.
    //
    // Eles vinham interpolados no template acima, e o template vai para o DOM
    // por insertAdjacentHTML — qualquer marcacao no meio viraria elemento de
    // verdade.
    //
    // Hoje NAO ha exploracao: varri as chamadas em 2026-07-30 e toda mensagem e
    // literal ou numero (`number_format`). Isto e fechar o cano antes, nao
    // remendo de vazamento — a mensagem obvia de amanha e "Empresa <razao
    // social> excluida", e a razao social vem do XML que o agente importa, ou
    // seja, de quem manda o arquivo.
    document.querySelector(".alert-title").textContent = title ?? "";
    document.querySelector(".alert-message").textContent = message ?? "";

    const alertWrapper = document.querySelector(".alert-wrapper");
    const alertFrame = document.querySelector(".alert-frame");
    const alertClose = document.querySelector(".alert-close");

    if (type === "question") {
      const confirmButton = document.querySelector(".confirm-button");
      const cancelButton = document.querySelector(".cancel-button");

      confirmButton.addEventListener("click", () => {
        alertWrapper.remove();
        resolve("confirm");
      });

      cancelButton.addEventListener("click", () => {
        alertWrapper.remove();
        resolve();
      });
    } else {
      const alertButton = document.querySelector(".alert-button");

      alertButton.addEventListener("click", () => {
        alertWrapper.remove();
        resolve();
      });
    }

    alertClose.addEventListener("click", () => {
      alertWrapper.remove();
      resolve();
    });

    alertWrapper.addEventListener("click", () => {
      alertWrapper.remove();
      resolve();
    });

    alertFrame.addEventListener("click", (e) => {
      e.stopPropagation();
    });
  });
}

const cuteToast = ({ type, message, timer = 5000 }) => {
  return new Promise((resolve) => {
    const existingToast = document.querySelector(".toast-container");

    if (existingToast) {
      existingToast.remove();
    }

    const body = document.querySelector("body");

    const scripts = document.getElementsByTagName("script");

    let src = "";

    for (let script of scripts) {
      if (script.src.includes("cute-alert.js")) {
        src = script.src.substring(0, script.src.lastIndexOf("/"));
      }
    }

    const template = `
    <div class="toast-container ${type}-bg">
      <div>
        <div class="toast-frame">
          <img class="toast-img" src="${src}/img/${type}.svg" />
          <span class="toast-message"></span>
          <div class="toast-close">X</div>
        </div>
        <div class="toast-timer ${type}-timer" style="animation: timer ${timer}ms linear;"/>
      </div>
    </div>
    `;

    body.insertAdjacentHTML("afterend", template);

    // Mesmo motivo do cuteAlert: a mensagem vai como TEXTO. Este e o caminho mais
    // provavel de receber nome de empresa ou de usuario um dia — e o toast que
    // confirma cadastro e exclusao.
    document.querySelector(".toast-message").textContent = message ?? "";

    const toastContainer = document.querySelector(".toast-container");

    setTimeout(() => {
      toastContainer.remove();
      resolve();
    }, timer);

    const toastClose = document.querySelector(".toast-close");

    toastClose.addEventListener("click", () => {
      toastContainer.remove();
      resolve();
    });
  });
}
