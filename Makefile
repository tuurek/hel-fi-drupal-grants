PHONY :=
PROJECT_DIR := $(dir $(lastword $(MAKEFILE_LIST)))

# Include project env vars (if exists)
-include .env
-include .env.local

# Include druidfi/tools config
include $(PROJECT_DIR)/tools/make/Makefile

# Include project specific make files (if they exist)
-include $(PROJECT_DIR)/tools/make/project/*.mk

# Project specific overrides for variables (if they exist)
-include $(PROJECT_DIR)/tools/make/override.mk

.PHONY: $(PHONY)

PHONY += migrate-tpr
migrate-tpr: ## Migrate data
	$(call step,Import TPR data....\n)
	$(call drush,migrate:import tpr_service_channel --update)
	$(call drush,migrate:import tpr_errand_service --update)
