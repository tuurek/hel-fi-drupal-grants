BUILD_TARGETS += js-install
CLEAN_FOLDERS += $(PACKAGE_JSON_PATH)/node_modules
JS_PACKAGE_MANAGER ?= yarn
JS_PACKAGE_MANAGER_CWD_FLAG_NPM ?= --prefix
JS_PACKAGE_MANAGER_CWD_FLAG_YARN ?= --cwd
INSTALLED_NODE_VERSION := $(shell command -v node > /dev/null && node --version | cut -c2-3 || echo no)
NODE_BIN := $(shell command -v node || echo no)
NPM_BIN := $(shell command -v npm || echo no)
YARN_BIN := $(shell command -v yarn || echo no)
NODE_VERSION ?= 16

PHONY += js-install
js-install: ## Install JS packages
	$(call step,Do $(JS_PACKAGE_MANAGER) install...)
ifeq ($(JS_PACKAGE_MANAGER),yarn)
	$(call node_run,install --frozen-lockfile)
else
	$(call node_run,install --engine-strict true)
endif

PHONY += js-outdated
js-outdated: ## Show outdated JS packages
	$(call step,Show outdated JS packages with $(JS_PACKAGE_MANAGER)...)
	$(call node_run,outdated)

define node_run
	$(call sub_step,Using local $(JS_PACKAGE_MANAGER)...\n)
	@$(JS_PACKAGE_MANAGER) $(if $(filter $(JS_PACKAGE_MANAGER),yarn),$(JS_PACKAGE_MANAGER_CWD_FLAG_YARN),$(JS_PACKAGE_MANAGER_CWD_FLAG_NPM)) $(PACKAGE_JSON_PATH) $(1)
endef

define NODE_VERSION_REQUIRED


🚫 You need to have Node version $(NODE_VERSION) on your host. You have $(INSTALLED_NODE_VERSION).

   Use 'nvm use $(NODE_VERSION)' to switch the Node version.


endef

ifneq ($(INSTALLED_NODE_VERSION),$(NODE_VERSION))
$(call error,$(NODE_VERSION_REQUIRED))
endif
