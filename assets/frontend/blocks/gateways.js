(() => {
  "use strict";
  const e = window.React,
    t = window.wc.wcBlocksRegistry,
    o = window.wp.htmlEntities,
    n = window.wc.wcSettings,
    r = (e) => document.querySelector(e),
    a = (e, t) => (e?.classList.add(t), e),
    s = (e, t) => (e?.classList.remove(t), e),
    l = (e) => (e?.style && (e.style.display = "none"), e);
  let c = {};
  const i = (e) => ((c = e), { ...d, order: c.order }),
    d = {
      getOrderInfo: () => {
        d.blockOnSubmit(),
          fetch(c.orderInfoUrl)
            .then((e) => e.json())
            .then((e) => {
              c.order = e.message;
            })
            .catch((e, t, o) => {
              console.log(o);
            })
            .finally(() => {
              d.unblockOnError();
            });
      },
      getPlaceOrderButtonSelector: () =>
        ".wc-block-components-checkout-place-order-button",
      getSubmitButtonTargetSelector: (e) => "#" + e + "-card-submit",
      getPaymentMethodRadioSelector: (e) =>
        '#payment-method input[id*="radio-control-wc-payment-method-options-' +
        e +
        '"]',
      getStoredPaymentMethodsRadioSelector: () =>
        '#payment-method input[id*="radio-control-wc-payment-method-saved-tokens-"]:checked',
      isOnlyGatewayMethodDisplayed: (e) => {
        const t = [
          ...('#payment-method input[id*="radio-control-wc-payment-method-"]',
            document.querySelectorAll(
              '#payment-method input[id*="radio-control-wc-payment-method-"]',
            )),
        ];
        const o = r(d.getPaymentMethodRadioSelector(e));
        return t.includes(o) && 1 === t.length;
      },
      isFirstPaymentMethod: (e) =>
        document.querySelector("#payment-method input").value === e,
      toggleSubmitButtons: () => {
        const e = r(
          '#payment-method input[id*="radio-control-wc-payment-method-options-"]:checked',
        )?.value;
        if (
          (s(r(".globalpayments.card-submit"), "is-active"), c.hide.includes(e))
        )
          return void d.hidePlaceOrderButton();
        if (!c.toggle.includes(e)) return void d.showPlaceOrderButton();
        const t = r("#" + e + "-card-submit"),
          o = r(d.getStoredPaymentMethodsRadioSelector()),
          n =
            e ===
            r(d.getStoredPaymentMethodsRadioSelector() + ":checked")?.value;
        !o || (o && n)
          ? (a(t, "is-active"), d.hidePlaceOrderButton())
          : (l(t), d.showPlaceOrderButton());
      },
      hidePlaceOrderButton: () => {
        l(
          a(
            r(d.getPlaceOrderButtonSelector()),
            "woocommerce-globalpayments-hidden",
          ),
        );
      },
      showPlaceOrderButton: () => {
        var e;
        (e = s(
          r(d.getPlaceOrderButtonSelector()),
          "woocommerce-globalpayments-hidden",
        )),
          e?.style && (e.style.display = "");
      },
      getFormSelector: () =>
        "form.wc-block-components-form.wc-block-checkout__form",
      createInputElement: (e, t, o) => {
        let n = document.getElementById(e + "-" + t);
        n ||
          ((n = document.createElement("input")),
            (n.id = e + "-" + t),
            (n.name = e + "[" + t + "]"),
            (n.type = "hidden"),
            r(d.getFormSelector()).appendChild(n)),
          (n.value = o);
      },
      createSubmitButtonTarget: (e) => {
        const t = document.createElement("div");
        (t.id = d.getSubmitButtonTargetSelector(e).replace("#", "")),
          (t.className = "globalpayments " + e + " card-submit");
        const o = r(d.getPlaceOrderButtonSelector());
        o?.parentNode.insertBefore(t, o.nextSibling), d.toggleSubmitButtons(e);
      },
      placeOrder: () => {
        try {
          const e = r(d.getPlaceOrderButtonSelector());
          if (e) return void e.click();
        } catch (e) { }
        r(d.getFormSelector()).submit();
      },
      showPaymentError: (e) => {
        const t = r(d.getFormSelector());
        r(
          ".woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-globalpayments-checkout-error",
        )?.remove(),
          -1 === e.indexOf("woocommerce-error") &&
          (e = '<ul class="woocommerce-error"><li>' + e + "</li></ul>"),
          t?.prepend(
            '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-globalpayments-checkout-error">' +
            e +
            "</div>",
          ),
          r("html, body").animate({ scrollTop: t.offsetTop - 100 }, 1e3),
          d.unblockOnError(),
          document.body.dispatchEvent(new Event("checkout_error"));
      },
      blockOnSubmit: () => {
        const e = r(d.getFormSelector()),
          t = e.data?.();
        1 !== t?.["blockUI.isBlocked"] &&
          e.block?.({
            message: null,
            overlayCSS: { background: "#fff", opacity: 0.6 },
          });
      },
      unblockOnError: () => {
        r(d.getFormSelector())?.unblock?.();
      },
      hidePaymentMethod: (e) => {
        delete (0, t.getPaymentMethods)()[e],
          window.wp.data
            .dispatch(window.wc.wcBlocksData.PAYMENT_STORE_KEY)
            .__internalRemoveAvailablePaymentMethod(e);
      },
      setPaymentMethodData: (e) => {
        window.wp.data
          .dispatch(window.wc.wcBlocksData.PAYMENT_STORE_KEY)
          .__internalSetPaymentMethodData(e);
      },
      dispatchInfo: ({
        message: e,
        context: t = "gp-wp-context",
        cb: o = null,
      }) => {
        window.wp.data
          .dispatch("core/notices")
          .createInfoNotice(e, { context: t }),
          o?.();
      },
      dispatchError: ({
        message: e,
        context: t = "gp-wp-context",
        cb: o = null,
      }) => {
        window.wp.data
          .dispatch("core/notices")
          .createErrorNotice(e, { context: t }),
          o?.();
      },
      hasValidationErrors: () =>
        window.wp.data
          .select(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
          .hasValidationErrors(),
    },
    { __ } = wp.i18n,
    { __: m } = wp.i18n,
    p = ({ state: e, helper: t, dispatchError: o, placeOrder: n }) => {
      var r;
      t.blockOnSubmit(), (e.settings.helper_params.order = t.order);
      const a = e.tokenResponse ? JSON.stringify(e.tokenResponse) : null,
        s =
          null !==
            (r = document.querySelector(
              t.getStoredPaymentMethodsRadioSelector(),
            )?.value) && void 0 !== r
            ? r
            : "new";
      return (
        GlobalPayments.ThreeDSecure.checkVersion(
          e.threedsecure.checkEnrollmentUrl,
          {
            tokenResponse: a,
            wcTokenId: s,
            order: {
              id: e.settings.helper_params.order.id,
              amount: e.settings.helper_params.order.amount,
              currency: e.settings.helper_params.order.currency,
            },
          },
        )
          .then((r) => {
            if (r.error) return o(r.message), !1;
            if ("NOT_ENROLLED" === r.status && "YES" !== r.liabilityShift)
              return o("Please try again with another card."), !1;
            if ("NOT_ENROLLED" === r.status && "YES" === r.liabilityShift)
              return n(), !0;
            const l = !document.querySelector(
              '.wc-block-checkout__use-address-for-billing input[type="checkbox"]',
            )?.checked,
              c = {
                streetAddress1:
                  document.querySelector("#billing-address_1")?.value,
                streetAddress2:
                  document.querySelector("#billing-address_2")?.value,
                city: document.querySelector("#billing-city")?.value,
                state: document.querySelector("#billing-state input")?.value,
                postalCode: document.querySelector("#billing-postcode")?.value,
                country: document.querySelector("#billing-country input")
                  ?.value,
              },
              i = l
                ? c
                : {
                  streetAddress1: document.querySelector(
                    "#shipping-address_1",
                  )?.value,
                  streetAddress2: document.querySelector(
                    "#shipping-address_2",
                  )?.value,
                  city: document.querySelector("#shipping-city")?.value,
                  state: document.querySelector("#shipping-state input")
                    ?.value,
                  postalCode:
                    document.querySelector("#shipping-postcode")?.value,
                  country: document.querySelector("#shipping-country input")
                    ?.value,
                };
            GlobalPayments.ThreeDSecure.initiateAuthentication(
              e.threedsecure.initiateAuthenticationUrl,
              {
                tokenResponse: a,
                wcTokenId: s,
                versionCheckData: r,
                challengeWindow: {
                  windowSize:
                    GlobalPayments.ThreeDSecure.ChallengeWindowSize
                      .Windowed500x600,
                  displayMode: "lightbox",
                },
                order: {
                  id: e.settings.helper_params.order.id,
                  amount: e.settings.helper_params.order.amount,
                  currency: e.settings.helper_params.order.currency,
                  billingAddress: c,
                  shippingAddress: i,
                  addressMatchIndicator: l,
                  customerEmail: document.querySelector(
                    ".wc-block-components-address-form__email input#email",
                  ).value,
                },
              },
            )
              .then((a) => {
                if (a.error) return o(a.message), !1;
                const s =
                  a.serverTransactionId ||
                  a.challenge.response.data.threeDSServerTransID ||
                  r.serverTransactionId;
                return (
                  t.createInputElement(e.settings.id, "serverTransId", s),
                  (e.serverTransId = s),
                  n(),
                  !0
                );
              })
              .catch(
                (e) => (
                  console.error(e),
                  console.error(e.reasons),
                  o("Something went wrong while doing 3DS processing."),
                  !1
                ),
              );
          })
          .catch(
            (e) => (
              console.error(e),
              console.error(e.reasons),
              o("Something went wrong while doing 3DS processing."),
              !1
            ),
          ),
        document.addEventListener("click", (e) => {
          e.target.matches('img[id^="GlobalPayments-frame-close-"]') &&
            window.parent.postMessage(
              { data: { transStatus: "N" }, event: "challengeNotification" },
              window.location.origin,
            );
        }),
        !1
      );
    },
    u = {
      settings: null,
      cardForm: null,
      tokenResponse: null,
      fieldOptions: null,
      serverTransId: null,
    };
  let g = {};
  let initRetryCount = 0;
  let isFormInitialized = false;
  const MAX_RETRY_COUNT = 20;
  const y = (t) => {
    const { id: o, field: n } = t;
    return (0, e.createElement)(
      "div",
      { className: `globalpayments ${o} ${n.class}` },
      (0, e.createElement)(
        "label",
        { htmlFor: `${o}-${n.class}` },
        n.label,
        (0, e.createElement)("span", { className: "required" }, ""),
      ),
      (0, e.createElement)("div", { id: `${o}-${n.class}` }),
    );
  },
    h = () => {
      // Check if form is already initialized
      if (isFormInitialized && u.cardForm) {
        console.log('GlobalPayments form is already initialized, skipping...');
        return;
      }

      // Check retry limit
      if (initRetryCount >= MAX_RETRY_COUNT) {
        console.error('Maximum retry attempts reached. GlobalPayments form initialization failed.');
        v('Payment form could not be loaded. Please refresh the page and try again.');
        return;
      }

      const e = u.settings.gateway_options;

      // Alternate payment options
      let acceptBlik = (u.settings.enable_blik === 'yes') ? true : false;
      let acceptOpenBanking = (u.settings.enable_bank_select === 'yes') ? true : false;

      let apmsEnabled = (acceptBlik || acceptOpenBanking) ? true : false;

      if (apmsEnabled) {
        var x = {
          apms: {
            currencyCode: "PLN",
            countryCode: "PL",
            nonCardPayments: {
              allowedPaymentMethods: [
                {
                  provider: GlobalPayments.enums.ApmProviders.Blik,
                  enabled: acceptBlik,
                },
              ]
            }
          },
        };

        // using push because Open Banking doesn't respect the 'enabled' property currently
        if (acceptOpenBanking) {
          x.apms.nonCardPayments.allowedPaymentMethods.push(
            {
              provider: GlobalPayments.enums.ApmProviders.OpenBanking,
              enabled: acceptOpenBanking,
              category: "TBD"
            }
          )
        }
      }

        e.error && v(e.message);

        // Create submit button if it doesn't exist
        document.querySelector(
          g.getSubmitButtonTargetSelector(u.settings.id),
        ) || g.createSubmitButtonTarget(u.settings.id);
        // Check if GlobalPayments is loaded
        if (typeof GlobalPayments === 'undefined') {
            console.warn('GlobalPayments is not loaded. Retrying in 500ms...');
            initRetryCount++;
            setTimeout(h, 500);
            return;
        }

        // Check if required GlobalPayments methods exist
        if (!GlobalPayments.configure || !GlobalPayments.creditCard || !GlobalPayments.creditCard.form) {
            console.warn('GlobalPayments methods not available. Retrying in 300ms...');
            initRetryCount++;
            setTimeout(h, 300);
            return;
        }

        // Check if field options are loaded
        if (!u.fieldOptions || !u.fieldOptions["payment-form"]) {
            console.warn('Field options not loaded. Retrying in 200ms...');
            initRetryCount++;
            setTimeout(h, 200);
            return;
        }

        // Check if the payment form container exists
        const formContainer = "#" + u.settings.id + "-" + u.fieldOptions["payment-form"].class;
        const containerElement = document.querySelector(formContainer);

        if (!containerElement) {
            console.warn('Payment form container not found:', formContainer, 'Retrying in 200ms...');
            initRetryCount++;
            setTimeout(h, 200);
            return;
        }

        // Check if form already exists in the container to prevent duplicates
        if (containerElement.children.length > 0) {
            console.log('Payment form already exists in container, marking as initialized');
            isFormInitialized = true;
            return;
        }

      // Reset retry count on successful dependency check
      initRetryCount = 0;

      let apmArray = (apmsEnabled) ? [] : false;

      try {
        const t = GlobalPayments;
        const ee = Object.assign(e, x);
        t.configure(ee),
          t.on("error", f),
          (u.cardForm = t.creditCard.form(
            "#" + u.settings.id + "-" + u.fieldOptions["payment-form"].class,
            {
              amount: u.settings.helper_params.order.amount,
              style: "gp-default",
              apms: apmArray,
            },
          )),
          // for blik click process
          u.cardForm.on(GlobalPayments.enums.ApmEvents.PaymentMethodSelection, paymentProviderData => {
            const {
              provider,
              countryCode,
              currencyCode,
              bankName
            } = paymentProviderData;
            console.log('Selected provider: ' + provider);

            let detail = {};

            switch (provider) {
              case GlobalPayments.enums.ApmProviders.Blik:
                g.setPaymentMethodData({ 'blik-payment': true });
                g.placeOrder();
                break;
              case GlobalPayments.enums.ApmProviders.OpenBanking:
                if (!bankName) {
                  detail = {
                    provider,
                    redirect_url: "https://fluentlenium.com/",
                    countryCode,
                    currencyCode,
                  }
                } else {
                  g.setPaymentMethodData({ 'OPEN_BANKING': bankName });
                  g.placeOrder();
                }
                break;
              default:
                detail = {
                  "seconds_to_expire": "900",
                  "next_action": "REDIRECT_IN_FRAME",
                  "redirect_url": 'https://google.com/',
                  provider,
                };
                break;
            }

            const merchantCustomEventProvideDetails = new CustomEvent(GlobalPayments.enums.ApmEvents.PaymentMethodActionDetail, {
              detail: detail
            });

            // may need to modify this in the future, but for now the only time for this event to fire
            // is when Open Banking payment option is clicked 
            if (!bankName) window.dispatchEvent(merchantCustomEventProvideDetails);

            // this prevents the page the checkout form refreshing when this button is clicked
            if (document.getElementById("select-another-payment-method-button")) {
              document.getElementById("select-another-payment-method-button").addEventListener("click", function(event) {
              event.preventDefault();
            });            
          }
        });

        // Mark form as initialized
        isFormInitialized = true;

        function apmClick(event) {
          event.preventDefault();
        }

        for (let item of document.getElementById("globalpayments_gpapi-payment-form").getElementsByTagName("button")) {
          item.addEventListener("click", apmClick, false)
        }

        // Add event handlers only if form was successfully created
        if (u.cardForm) {
          u.cardForm.on("submit", "click", () => {
            w(), b(), g.blockOnSubmit();
          });
          u.cardForm.on("token-success", T);
          u.cardForm.on("token-error", f);
          u.cardForm.on("error", f);
          u.cardForm.on("card-form-validity", (e) => {
            e || (g.unblockOnError(), S(), _());
          });
          u.cardForm.ready(() => {
            g.toggleSubmitButtons();
          });
        } else {
          console.error('Failed to create GlobalPayments form');
          isFormInitialized = false;
          v('Payment form initialization failed. Please refresh the page.');
        }
      } catch (error) {
        console.error('Error initializing GlobalPayments form:', error);
        isFormInitialized = false;
        v('Payment system initialization failed. Please refresh the page.');
        return;
      }
    },
    w = () => {
      const e = document.querySelector(
        g.getSubmitButtonTargetSelector(u.settings.id),
      );
      e.classList.add("wc-block-components-spinner"),
        e.classList.add("wc-block-components-checkout-step--disabled"),
        (e.style.height = "initial !important"),
        (e.style.width = "initial !important"),
        (e.style.position = "relative");
    },
    S = () => {
      const e = document.querySelector(
        g.getSubmitButtonTargetSelector(u.settings.id),
      );
      e.classList.remove("wc-block-components-spinner"),
        e.classList.remove("wc-block-components-checkout-step--disabled"),
        (e.style.height = "initial !important"),
        (e.style.width = "initial !important"),
        (e.style.position = "relative");
    },
    b = () => {
      document
        .querySelector(g.getFormSelector())
        .classList.add("wc-block-components-checkout-step--disabled"),
        document.querySelectorAll(`${g.getFormSelector()} > *`).forEach((e) => {
          e.style.pointerEvents = "none";
        });
    },
    _ = () => {
      document
        .querySelector(g.getFormSelector())
        .classList.remove("wc-block-components-checkout-step--disabled"),
        document.querySelectorAll(`${g.getFormSelector()} > *`).forEach((e) => {
          e.style.pointerEvents = "";
        });
    },
    v = (e) => {
      g.dispatchError({
        message: e,
        cb: () => {
          S(), _();
        },
      });
    },
    k = (e) => {
      v(u.settings.secure_payment_fields[e].messages.validation),
        g.unblockOnError();
    },
    f = (e) => {
      if (e.reasons)
        for (let t = 0; t < e.reasons.length; t++) {
          const o = e.reasons[t];
          "NOT_AUTHENTICATED" === o.code
            ? v(
              "We're not able to process this payment. Please refresh the page and try again.",
            )
            : v(o.message);
        }
      else v("Something went wrong. Please contact us to get assistance.");
    },
    P = () => {
      const e = {
        "card-number": {
          placeholder: u.fieldOptions["card-number-field"].placeholder,
          target:
            "#" +
            u.settings.id +
            "-" +
            u.fieldOptions["card-number-field"].class,
        },
        "card-expiration": {
          placeholder: u.fieldOptions["card-expiry-field"].placeholder,
          target:
            "#" +
            u.settings.id +
            "-" +
            u.fieldOptions["card-expiry-field"].class,
        },
        "card-cvv": {
          placeholder: u.fieldOptions["card-cvc-field"].placeholder,
          target:
            "#" + u.settings.id + "-" + u.fieldOptions["card-cvc-field"].class,
        },
        submit: { text: E(), target: "#" + u.settings.id + "-card-submit" },
      };
      return (
        u.fieldOptions.hasOwnProperty("card-holder-name-field") &&
        (e["card-holder-name"] = {
          placeholder: u.fieldOptions["card-holder-name-field"].placeholder,
          target:
            "#" +
            u.settings.id +
            "-" +
            u.fieldOptions["card-holder-name-field"].class,
        }),
        e
      );
    },
    E = () => document.querySelector(g.getPlaceOrderButtonSelector()).innerText,
    O = () => JSON.parse(u.settings.field_styles),
    T = (e) => {
      if (g.hasValidationErrors())
        return (
          S(),
          _(),
          window.wp.data
            .dispatch(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
            .showAllValidationErrors(),
          void document.querySelector(".has-error")?.scrollIntoView({
            behavior: "smooth",
            block: "center",
            inline: "start",
          })
        );
      q(e) && ((u.tokenResponse = e), C());
    },
    C = () => {
      u.settings.gateway_options.enableThreeDSecure
        ? (g.getOrderInfo(),
          p({ state: u, helper: g, dispatchError: v, placeOrder: M }))
        : M();
    },
    M = () => {
      "function" == typeof u.cardForm.frames["card-cvv"].getCvv
        ? u.cardForm.frames["card-cvv"].getCvv().then((e) => {
          u.tokenResponse && (u.tokenResponse.details.cardSecurityCode = e),
            B();
        })
        : B();
    },
    q = (e) => {
      if (e.details) {
        const t = new Date(e.details.expiryYear, e.details.expiryMonth - 1),
          o = new Date(),
          n = new Date(o.getFullYear(), o.getMonth());
        if (!e.details.expiryYear || !e.details.expiryMonth || t < n)
          return k("card-expiry-field"), !1;
      }
      return !(
        e.details &&
        !e.details.cardSecurityCode &&
        (k("card-cvc-field"), 1)
      );
    },
    B = () => {
      const e = { token_response: JSON.stringify(u.tokenResponse) };
      u.serverTransId && (e.serverTransId = u.serverTransId),
        g.setPaymentMethodData(e),
        g.placeOrder();
    },
    { __: A } = wp.i18n,
    I = { order: null, settings: null };
  let D = {};
  const F = () => {
    D.toggleSubmitButtons(), N();
  },
    N = () => {
      D.createSubmitButtonTarget(I.settings.id);
      const e = document.createElement("div");
      (e.className =
        "apple-pay-button apple-pay-button-" +
        I.settings.payment_method_options.button_color),
        (e.title = A(
          "Pay with Apple Pay",
          "globalpayments-gateway-provider-for-woocommerce",
        )),
        (e.alt = A(
          "Pay with Apple Pay",
          "globalpayments-gateway-provider-for-woocommerce",
        )),
        (e.id = I.settings.id),
        e.addEventListener("click", (e) => {
          if ((e.preventDefault(), D.hasValidationErrors()))
            return (
              window.wp.data
                .dispatch(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
                .showAllValidationErrors(),
              void document.querySelector(".has-error")?.scrollIntoView({
                behavior: "smooth",
                block: "center",
                inline: "start",
              })
            );
          R().begin();
        }),
        document
          .querySelector(D.getSubmitButtonTargetSelector(I.settings.id))
          .append(e);
    },
    R = () => {
      D.getOrderInfo(), (I.order = D.order);
      let e = null;
      try {
        e = new ApplePaySession(1, L());
      } catch (e) {
        return (
          console.error(
            A(
              "Unable to create ApplePaySession",
              "globalpayments-gateway-provider-for-woocommerce",
            ),
            e,
          ),
          alert(
            A(
              "We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.",
              "globalpayments-gateway-provider-for-woocommerce",
            ),
          ),
          !1
        );
      }
      return (
        (e.onvalidatemerchant = (t) => {
          Y(t, e);
        }),
        (e.onpaymentauthorized = (t) => {
          U(t, e);
        }),
        (e.oncancel = (e) => {
          alert(
            A(
              "We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.",
              "globalpayments-gateway-provider-for-woocommerce",
            ),
          );
        }),
        e
      );
    },
    L = () => ({
      countryCode: x(),
      currencyCode: I.order.currency,
      merchantCapabilities: ["supports3DS"],
      supportedNetworks: V(),
      total: { label: G(), amount: I.order.amount.toString() },
      requiredBillingContactFields: ["postalAddress", "name"],
    }),
    x = () => I.settings.payment_method_options.country_code,
    V = () => I.settings.payment_method_options.cc_types,
    G = () => I.settings.payment_method_options.apple_merchant_display_name,
    Y = (e, t) => {
      fetch(I.settings.payment_method_options.validate_merchant_url, {
        cache: "no-store",
        body: JSON.stringify({ validationUrl: e.validationURL }),
        method: "POST",
      })
        .then((e) => e.json())
        .then((e) => {
          e.error
            ? (console.log("response", e),
              t.abort(),
              alert(
                A(
                  "We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.",
                  "globalpayments-gateway-provider-for-woocommerce",
                ),
              ))
            : t.completeMerchantValidation(JSON.parse(e.message));
        })
        .catch((e) => {
          console.log("response", e),
            t.abort(),
            alert(
              A(
                "We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.",
                "globalpayments-gateway-provider-for-woocommerce",
              ),
            );
        });
    },
    U = (e, t) => {
      try {
        const o = { dw_token: JSON.stringify(e.payment.token.paymentData) };
        e.payment.billingContact &&
          (o.cardHolderName =
            e.payment.billingContact.givenName +
            " " +
            e.payment.billingContact.familyName),
          D.setPaymentMethodData(o);
        const n = document.querySelector(D.getPlaceOrderButtonSelector());
        if (n)
          return (
            n.click(), void t.completePayment(ApplePaySession.STATUS_SUCCESS)
          );
      } catch (e) {
        t.completePayment(ApplePaySession.STATUS_FAILURE);
      }
      t.completePayment(ApplePaySession.STATUS_SUCCESS),
        document.querySelector(D.getFormSelector()).submit();
    },
    { __: W } = wp.i18n,
    $ = { order: null, ctpForm: null, settings: null };
  let J = {};
  const j = () => {
    if ((J.toggleSubmitButtons(), K(), !GlobalPayments.configure))
      return void console.log(
        W(
          "Warning! Payment fields cannot be loaded",
          "globalpayments-gateway-provider-for-woocommerce",
        ),
      );
    const e = $.settings.payment_method_options;
    J.getOrderInfo(),
      ($.order = J.order),
      (e.apms.currencyCode = $.order.currency),
      GlobalPayments.configure(e),
      GlobalPayments.on("error", z),
      ($.ctpForm = GlobalPayments.apm.form("#" + $.settings.id, {
        amount: $.order.amount.toString(),
        style: "gp-default",
        apms: [GlobalPayments.enums.Apm.ClickToPay],
      })),
      $.ctpForm.on("token-success", H),
      $.ctpForm.on("token-error", z),
      $.ctpForm.on("error", z);
  },
    K = () => {
      const e = document.querySelector("#" + $.settings.id);
      if (e?.children.length > 0) {
        for (; e.firstChild;) e.removeChild(e.lastChild);
        document.querySelector("#" + $.settings.id + "-dw_token")?.remove();
      }
    },
    z = (e) => {
      console.error(e);
    },
    H = (e) => (
      J.setPaymentMethodData({ dw_token: e.paymentReference }), J.placeOrder()
    ),
    Q = { order: null, settings: null, paymentsClient: null };
  let X = {};
  const Z = () => {
    ee(),
      Q.paymentsClient
        .isReadyToPay(oe())
        .then((e) => {
          e.result
            ? le(Q.settings.id)
            : (X.hidePaymentMethod(Q.settings.id), X.hidePlaceOrderButton());
        })
        .catch(console.error);
  },
    ee = () => {
      null === Q.paymentsClient &&
        (Q.paymentsClient = new google.payments.api.PaymentsClient({
          environment: te(),
        }));
    },
    te = () => Q.settings.payment_method_options.env,
    oe = () => Object.assign({}, ne(), { allowedPaymentMethods: [re()] }),
    ne = () => ({ apiVersion: 2, apiVersionMinor: 0 }),
    re = () => ({
      type: "CARD",
      parameters: {
        allowedAuthMethods: ae(),
        allowedCardNetworks: se(),
        billingAddressRequired: !0,
      },
    }),
    ae = () => Q.settings.payment_method_options.aca_methods,
    se = () => Q.settings.payment_method_options.cc_types,
    le = () => {
      X.createSubmitButtonTarget(Q.settings.id);
      const e = Q.paymentsClient.createButton({
        buttonColor: ce(),
        onClick: () => {
          ie();
        },
      });
      document
        .querySelector(X.getSubmitButtonTargetSelector(Q.settings.id))
        .append(e);
    },
    ce = () => Q.settings.payment_method_options.button_color,
    ie = () => {
      if (X.hasValidationErrors())
        return (
          window.wp.data
            .dispatch(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
            .showAllValidationErrors(),
          void document.querySelector(".has-error")?.scrollIntoView({
            behavior: "smooth",
            block: "center",
            inline: "start",
          })
        );
      X.getOrderInfo(), (Q.order = X.order);
      const e = de();
      Q.paymentsClient
        .loadPaymentData(e)
        .then(
          (e) => (
            X.setPaymentMethodData({
              dw_token: JSON.stringify(
                JSON.parse(e.paymentMethodData.tokenizationData.token),
              ),
              cardHolderName: e.paymentMethodData.info.billingAddress.name,
            }),
            X.placeOrder()
          ),
        )
        .catch(console.error);
    },
    de = () => {
      const e = Object.assign({}, ne());
      return (
        (e.allowedPaymentMethods = [me()]),
        (e.transactionInfo = ue()),
        (e.merchantInfo = { merchantId: ge(), merchantName: ye() }),
        e
      );
    },
    me = () => Object.assign({}, re(), { tokenizationSpecification: pe() }),
    pe = () => ({
      type: "PAYMENT_GATEWAY",
      parameters: {
        gateway: "globalpayments",
        gatewayMerchantId:
          Q.settings.payment_method_options.global_payments_merchant_id,
      },
    }),
    ue = () => ({
      totalPriceStatus: "FINAL",
      totalPrice: Q.order.amount.toString(),
      currencyCode: Q.order.currency,
    }),
    ge = () => Q.settings.payment_method_options.google_merchant_id,
    ye = () => Q.settings.payment_method_options.google_merchant_name,
    he = { settings: null, onSubmit: null, serverTransId: "" };
  let we = {};
  const Se = () => {
    document.querySelector("#wc-block-components-spinner")?.remove();
  },
    be = () => {
      document
        .querySelector(we.getFormSelector())
        .classList.remove("wc-block-components-checkout-step--disabled"),
        document
          .querySelectorAll(`${we.getFormSelector()} > *`)
          .forEach((e) => {
            e.style.pointerEvents = "";
          });
    },
    _e = (e) => {
      we.dispatchError({
        message: e,
        cb: () => {
          Se(), be();
        },
      });
    },
    ve = () => {
      if (we.hasValidationErrors())
        return (
          Se(),
          be(),
          void document.querySelector(".has-error").scrollIntoView({
            behavior: "smooth",
            block: "center",
            inline: "start",
          })
        );
      he.settings.gateway_options.enableThreeDSecure
        ? (we.getOrderInfo(),
          (() => {
            const e = document.querySelector(we.getPlaceOrderButtonSelector()),
              t = document.createElement("span");
            (t.id = "wc-block-components-spinner"),
              t.classList.add("wc-block-components-spinner"),
              e.appendChild(t);
          })(),
          document
            .querySelector(we.getFormSelector())
            .classList.add("wc-block-components-checkout-step--disabled"),
          document
            .querySelectorAll(`${we.getFormSelector()} > *`)
            .forEach((e) => {
              e.style.pointerEvents = "none";
            }),
          p({
            state: he,
            helper: we,
            dispatchError: _e,
            placeOrder: () => {
              const e = window.wp.data
                .select(window.wc.wcBlocksData.PAYMENT_STORE_KEY)
                .getPaymentMethodData();
              (e.serverTransId = he.serverTransId),
                we.setPaymentMethodData(e),
                he.onSubmit();
            },
          }))
        : he.onSubmit();
    },
    { __: ke } = wp.i18n,
    fe = [
      {
        id: "globalpayments_gpapi",
        Content: (0, e.createElement)(
          (t) => {
            const { id: o, eventRegistration: r } = t,
              a = (0, n.getSetting)(o + "_data", {}),
              s = Object.entries(a.secure_payment_fields),
              { StoreNoticesContainer: l } = window.wc.blocksCheckout;
            (g = i(a.helper_params)),
              (u.settings = a),
              (u.fieldOptions = a.secure_payment_fields),
              (u.threedsecure = a.threedsecure);
            const { onCheckoutFail: c } = r;
            return (
              (0, e.useEffect)(() => {
                document
                    .querySelector("#order_review, #add_payment_method")
                    ?.addEventListener("click", (e) => {
                        e.target.matches('#payment-method input[type="radio"]') &&
                        g.toggleSubmitButtons();
                    }),
                    document.body.addEventListener("checkout_error", () => {
                        document
                            .querySelector("#globalpayments_gpapi-serverTransId")
                            ?.remove();
                    }),
                    document.addEventListener(
                        "globalpayments_pay_order_modal_loaded",
                        h,
                    ),
                    document.addEventListener(
                        "globalpayments_pay_order_modal_error",
                        (e, t) => {
                            g.showPaymentError(t);
                        },
                    );

                    // Initialize payment form when the payment method is first/only or when document is ready
                    const initializeForm = () => {

                        // Try immediate initialization first
                        h();

                        // If form isn't initialized yet, try again with delays
                        if (!isFormInitialized) {
                            setTimeout(() => {
                                if (!isFormInitialized) h();
                            }, 500);

                            setTimeout(() => {
                                if (!isFormInitialized) h();
                            }, 1000);
                        }
                    };

                    if (g.isFirstPaymentMethod(u.settings.id) || g.isOnlyGatewayMethodDisplayed(u.settings.id)) {

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initializeForm);
                            window.addEventListener('load', initializeForm);
                        } else if (document.readyState === 'interactive') {
                            setTimeout(initializeForm, 100);
                            window.addEventListener('load', initializeForm);
                        } else {
                            // Document is already complete
                            initializeForm();
                        }
                    } else {
                        console.log('Not the first payment method, will wait for user selection');
                    }

                    // Add event listener for payment method change
                    const radioElement = document.querySelector(
                        "#radio-control-wc-payment-method-options-globalpayments_gpapi",
                    );

                    const handlePaymentMethodChange = () => {
                        console.log('Payment method changed to GlobalPayments');
                        // Reset initialization state when payment method changes
                        isFormInitialized = false;
                        initRetryCount = 0;
                        h();
                    };

                    if (radioElement) {
                        radioElement.addEventListener("change", handlePaymentMethodChange);
                    } else {
                        // If radio element doesn't exist yet, try again after a delay
                        setTimeout(() => {
                            const delayedRadioElement = document.querySelector(
                                "#radio-control-wc-payment-method-options-globalpayments_gpapi",
                            );
                            if (delayedRadioElement) {
                                delayedRadioElement.addEventListener("change", handlePaymentMethodChange);
                            }
                        }, 500);
                    }
                    const e = c(() => {
                        _(), S();
                    });
                    return () => {
                        document.querySelector(
                            g.getPaymentMethodRadioSelector(u.settings.id),
                        )?.checked ||
                        (document
                            .querySelector(
                                g.getSubmitButtonTargetSelector(u.settings.id),
                            )
                            ?.remove(),
                            g.showPlaceOrderButton()),
                            e();
                    };
                }, [c]),
                    (0, e.createElement)(
                        e.Fragment,
                        null,
                        (0, e.createElement)("div", {
                            dangerouslySetInnerHTML: {
                                __html: u.settings.environment_indicator,
                            },
                        }),
                        s.map((t) =>
                            (0, e.createElement)(y, { id: o, field: t[1], key: t[0] }),
                        ),
                        (0, e.createElement)(l, { context: "gp-wp-context" }),
                    )
            );
          },
          { id: "globalpayments_gpapi" },
        ),
        SavedTokenComponent: (0, e.createElement)(
          (t) => {
            const { id: o, onSubmit: r, eventRegistration: a } = t;
            he.onSubmit = r;
            const s = (0, n.getSetting)(o + "_data", {});
            (we = i(s.helper_params)),
              (he.settings = s),
              (he.threedsecure = s.threedsecure);
            const { onCheckoutFail: l } = a;
            (0, e.useEffect)(() => {
              window.onload = () => {
                document
                  .querySelector(we.getPlaceOrderButtonSelector())
                  .addEventListener("click", (e) => {
                    document.querySelector(
                      we.getStoredPaymentMethodsRadioSelector(),
                    ) &&
                      (e.preventDefault(), e.stopImmediatePropagation(), ve());
                  });
              };
              const e = l(() => {
                be(), Se();
              });
              return () => {
                e();
              };
            }, [l]);
            const { StoreNoticesContainer: c } = window.wc.blocksCheckout;
            return (0, e.createElement)(c, { context: "gp-wp-context" });
          },
          { id: "globalpayments_gpapi" },
        ),
        canMakePayment: (e) =>
          !e.gateway_options.hide ||
          !e.gateway_options.error ||
          (console.error(e.gateway_options.message), !1),
      },
      {
        id: "globalpayments_clicktopay",
        Content: (0, e.createElement)(
          () => (
            ($.settings = (0, n.getSetting)(
              "globalpayments_clicktopay_data",
              {},
            )),
            (J = i($.settings.helper_params)),
            (0, e.useEffect)(
              () => (
                (J.isFirstPaymentMethod($.settings.id) ||
                  J.isOnlyGatewayMethodDisplayed($.settings.id)) &&
                (window.onload = () => {
                  j();
                }),
                document
                  .querySelector(
                    "#radio-control-wc-payment-method-options-globalpayments_clicktopay",
                  )
                  .addEventListener("change", j),
                () => {
                  document.querySelector(
                    J.getPaymentMethodRadioSelector($.settings.id),
                  )?.checked || J.toggleSubmitButtons();
                }
              ),
            ),
            (0, e.createElement)(
              e.Fragment,
              null,
              (0, e.createElement)("div", { id: $.settings.id }),
            )
          ),
          null,
        ),
        SavedTokenComponent: null,
        canMakePayment: (e) =>
          !e.payment_method_options.error ||
          (console.error(e.payment_method_options.message), !1),
      },
      {
        id: "globalpayments_googlepay",
        Content: (0, e.createElement)((t) => {
          const { eventRegistration: o } = t;
          (Q.settings = (0, n.getSetting)("globalpayments_googlepay_data", {})),
            (X = i(Q.settings.helper_params));
          const { onCheckoutFail: r } = o;
          (0, e.useEffect)(() => {
            (X.isFirstPaymentMethod(Q.settings.id) ||
              X.isOnlyGatewayMethodDisplayed(Q.settings.id)) &&
              (window.onload = () => {
                Z();
              }),
              document
                .querySelector(
                  "#radio-control-wc-payment-method-options-globalpayments_googlepay",
                )
                .addEventListener("change", Z);
            const e = r(() => {
              document.querySelector(".is-error")?.scrollIntoView({
                behavior: "smooth",
                block: "center",
                inline: "start",
              });
            });
            return () => {
              document.querySelector(
                X.getPaymentMethodRadioSelector(Q.settings.id),
              )?.checked ||
                (document
                  .querySelector(X.getSubmitButtonTargetSelector(Q.settings.id))
                  ?.remove(),
                  X.toggleSubmitButtons()),
                e();
            };
          }, [r]);
        }, null),
        SavedTokenComponent: null,
        canMakePayment: () => !0,
      },
      {
        id: "globalpayments_applepay",
        Content: (0, e.createElement)(() => {
          (I.settings = (0, n.getSetting)("globalpayments_applepay_data", {})),
            (D = i(I.settings.helper_params)),
            (0, e.useEffect)(
              () => (
                (D.isFirstPaymentMethod(I.settings.id) ||
                  D.isOnlyGatewayMethodDisplayed(I.settings.id)) &&
                (window.onload = () => {
                  F();
                }),
                document
                  .querySelector(
                    "#radio-control-wc-payment-method-options-globalpayments_applepay",
                  )
                  .addEventListener("change", F),
                () => {
                  document.querySelector(
                    D.getPaymentMethodRadioSelector(I.settings.id),
                  )?.checked ||
                    (document
                      .querySelector(
                        D.getSubmitButtonTargetSelector(I.settings.id),
                      )
                      ?.remove(),
                      D.toggleSubmitButtons());
                }
              ),
            );
        }, null),
        SavedTokenComponent: null,
        canMakePayment: () =>
          "https:" !== location.protocol
            ? (console.warn(
              ke(
                "Apple Pay requires your checkout be served over HTTPS",
                "globalpayments-gateway-provider-for-woocommerce",
              ),
            ),
              !1)
            : !0 ===
            (window.ApplePaySession && ApplePaySession.canMakePayments()) ||
            (console.warn(
              ke(
                "Apple Pay is not supported on this device/browser",
                "globalpayments-gateway-provider-for-woocommerce",
              ),
            ),
              !1),
      },
      {
        id: "globalpayments_affirm",
        Content: (0, e.createElement)(
          (t) => {
            const { id: o, eventRegistration: r } = t,
              { onPaymentSetup: a } = r,
              s = (0, n.getSetting)(o + "_data", {});
            i(s.helper_params).showPlaceOrderButton(),
              (0, e.useEffect)(() => {
                const e = a(() => {
                  const e = document.getElementById("shipping-phone"),
                    t = document.getElementById("billing-phone");
                  return (
                    !(!e?.value && !t?.value) || {
                      type: "error",
                      message: m(
                        "<strong>Phone</strong> is a required field for this payment method.",
                        "globalpayments-gateway-provider-for-woocommerce",
                      ),
                    }
                  );
                });
                return () => {
                  e();
                };
              }, [a]);
          },
          { id: "globalpayments_affirm" },
        ),
        SavedTokenComponent: null,
        canMakePayment: () => !0,
      },
      {
        id: "globalpayments_klarna",
        Content: (0, e.createElement)(
          (t) => {
            const { id: o, eventRegistration: r } = t,
              { onPaymentSetup: a } = r,
              s = (0, n.getSetting)(o + "_data", {});
            i(s.helper_params).showPlaceOrderButton(),
              (0, e.useEffect)(() => {
                const e = a(() => {
                  const e = document.getElementById("shipping-phone"),
                    t = document.getElementById("billing-phone");
                  return (
                    !(!e?.value && !t?.value) || {
                      type: "error",
                      message: __(
                        "<strong>Phone</strong> is a required field for this payment method.",
                        "globalpayments-gateway-provider-for-woocommerce",
                      ),
                    }
                  );
                });
                return () => {
                  e();
                };
              }, [a]);
          },
          { id: "globalpayments_klarna" },
        ),
        SavedTokenComponent: null,
        canMakePayment: () => !0,
      },
      {
        id: "globalpayments_bankpayment",
        Content: (0, e.createElement)(
          ({ id: e }) => {
            const t = (0, n.getSetting)(e + "_data", {});
            i(t.helper_params).showPlaceOrderButton();
          },
          { id: "globalpayments_bankpayment" },
        ),
        SavedTokenComponent: null,
        canMakePayment: () => !0,
      },
      {
        id: "globalpayments_paypal",
        Content: (0, e.createElement)(
          ({ id: e }) => {
            const t = (0, n.getSetting)(e + "_data", {});
            i(t.helper_params).showPlaceOrderButton();
          },
          { id: "globalpayments_paypal" },
        ),
        SavedTokenComponent: null,
        canMakePayment: () => !0,
      },
    ],
    Pe = (0, t.getPaymentMethods)();
  fe.forEach((r) => {
    Pe.hasOwnProperty(r.id) ||
      ((r) => {
        const {
          id: a,
          Content: s,
          SavedTokenComponent: l,
          canMakePayment: c,
        } = r,
          i = (0, n.getSetting)(a + "_data", {});
        if (0 === Object.entries(i).length) return;
        const d = (0, o.decodeEntities)(i.title),
          m = {
            name: a,
            label: (0, e.createElement)((t) => {
              const { PaymentMethodLabel: o } = t.components;
              return (0, e.createElement)(o, { text: d });
            }, null),
            content: s,
            edit: s,
            savedTokenComponent: l,
            canMakePayment: () => c(i),
            ariaLabel: d,
            supports: {
              features: i.supports,
              showSaveOption: i.allow_card_saving,
            },
          };
        (0, t.registerPaymentMethod)(m);
      })(r);
  });
})();
