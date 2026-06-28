#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <pwd.h>
#include <limits.h>
#include <sys/stat.h>

void execute_chattr(const char *chattr_path, int recursive, const char *mode, const char *path) {
    pid_t pid = fork();
    if (pid == 0) {
        // Child process: run chattr silently
        FILE *dev_null = fopen("/dev/null", "w");
        if (dev_null) {
            dup2(fileno(dev_null), STDOUT_FILENO);
            dup2(fileno(dev_null), STDERR_FILENO);
            fclose(dev_null);
        }
        
        if (recursive) {
            execl(chattr_path, chattr_path, "-R", mode, path, NULL);
        } else {
            execl(chattr_path, chattr_path, mode, path, NULL);
        }
        exit(1);
    } else if (pid > 0) {
        int status;
        waitpid(pid, &status, 0);
    }
}

int main(int argc, char *argv[]) {
    // 1. Elevate effective privileges to root
    if (setuid(0) != 0) {
        perror("Error: Failed to setuid to root");
        return 1;
    }

    // 2. Check at least action arg is present
    if (argc < 2) {
        fprintf(stderr, "Usage:\n  %s <lock|unlock|update> <site_path>\n  %s get_domain_config <username> <domain> <subdomains|conf>\n  %s read_log <username> <domain> <access|error> <lines>\n", argv[0], argv[0], argv[0]);
        return 1;
    }

    const char *action = argv[1];

    // 3. Handle 'update' early — only needs 1 arg (the action itself)
    if (strcmp(action, "update") == 0) {
        const char *update_sh = "/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/self_update.sh";
        if (access(update_sh, X_OK) != 0) {
            fprintf(stderr, "Error: self_update.sh not found or not executable at %s\n", update_sh);
            return 1;
        }
        execl("/bin/bash", "bash", update_sh, NULL);
        perror("Error: execl failed");
        return 1;
    }

    // 4. All other actions need at least 3 args
    if (argc < 3) {
        fprintf(stderr, "Usage:\n  %s <lock|unlock|update> <site_path>\n  %s get_domain_config <username> <domain> <subdomains|conf>\n", argv[0], argv[0]);
        return 1;
    }

    // 3. Identify the calling user (real UID)
    uid_t uid = getuid();
    struct passwd *pw = getpwuid(uid);
    if (!pw) {
        fprintf(stderr, "Error: Failed to identify user from UID %d.\n", uid);
        return 1;
    }
    
    // Handle get_domain_config subcommand
    if (strcmp(action, "get_domain_config") == 0) {
        if (argc != 5) {
            fprintf(stderr, "Usage: %s get_domain_config <username> <domain> <subdomains|conf>\n", argv[0]);
            return 1;
        }
        const char *target_user = argv[2];
        const char *domain = argv[3];
        const char *type = argv[4];
        
        // Security check: Only root and diradmin can query other users' domain configurations
        if (uid != 0 && strcmp(pw->pw_name, "diradmin") != 0) {
            if (strcmp(target_user, pw->pw_name) != 0) {
                fprintf(stderr, "Error: Access denied. You can only query your own configurations.\n");
                return 1;
            }
        }
        
        // Validate target_user to prevent path traversal
        for (const char *p = target_user; *p; p++) {
            if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') || (*p >= '0' && *p <= '9') || *p == '-' || *p == '_')) {
                fprintf(stderr, "Error: Invalid username characters.\n");
                return 1;
            }
        }
        
        if (strcmp(type, "subdomains") != 0 && strcmp(type, "conf") != 0 &&
            strcmp(type, "cust_httpd") != 0 && strcmp(type, "cust_nginx") != 0 &&
            strcmp(type, "cust_openlitespeed") != 0 && strcmp(type, "cust_apache") != 0 &&
            strcmp(type, "subdomains.docroot.override") != 0) {
            fprintf(stderr, "Error: Invalid config type. Use 'subdomains', 'conf', 'cust_httpd', 'cust_nginx', 'cust_openlitespeed', 'cust_apache', or 'subdomains.docroot.override'.\n");
            return 1;
        }
        
        // Validate domain name to prevent path traversal
        for (const char *p = domain; *p; p++) {
            if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') || (*p >= '0' && *p <= '9') || *p == '.' || *p == '-')) {
                fprintf(stderr, "Error: Invalid domain name characters.\n");
                return 1;
            }
        }
        
        char path[PATH_MAX];
        snprintf(path, sizeof(path), "/usr/local/directadmin/data/users/%s/domains/%s.%s", target_user, domain, type);
        
        FILE *f = fopen(path, "r");
        if (!f) {
            // File might not exist (e.g. no subdomains configured yet), exit silently
            return 0;
        }
        
        char buf[1024];
        while (fgets(buf, sizeof(buf), f)) {
            printf("%s", buf);
        }
        fclose(f);
        return 0;
    }

    // Handle read_log subcommand
    if (strcmp(action, "read_log") == 0) {
        if (argc != 6) {
            fprintf(stderr, "Usage: %s read_log <username> <domain> <access|error> <lines>\n", argv[0]);
            return 1;
        }
        const char *target_user = argv[2];
        const char *domain = argv[3];
        const char *log_type = argv[4];
        const char *lines_str = argv[5];
        
        // Security check: Only root and diradmin can read logs of other users
        if (uid != 0 && strcmp(pw->pw_name, "diradmin") != 0) {
            if (strcmp(target_user, pw->pw_name) != 0) {
                fprintf(stderr, "Error: Access denied. You can only read logs for your own domain.\n");
                return 1;
            }
        }
        
        // Validate target_user
        for (const char *p = target_user; *p; p++) {
            if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') || (*p >= '0' && *p <= '9') || *p == '-' || *p == '_')) {
                fprintf(stderr, "Error: Invalid username.\n");
                return 1;
            }
        }
        
        // Validate domain
        for (const char *p = domain; *p; p++) {
            if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') || (*p >= '0' && *p <= '9') || *p == '.' || *p == '-')) {
                fprintf(stderr, "Error: Invalid domain.\n");
                return 1;
            }
        }
        
        // Validate log_type
        if (strcmp(log_type, "access") != 0 && strcmp(log_type, "error") != 0) {
            fprintf(stderr, "Error: Invalid log type. Use 'access' or 'error'.\n");
            return 1;
        }
        
        // Validate lines
        int lines = atoi(lines_str);
        if (lines <= 0 || lines > 5000) {
            fprintf(stderr, "Error: Invalid line count. Must be between 1 and 5000.\n");
            return 1;
        }
        
        const char *read_log_sh = "/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/read_log.sh";
        if (access(read_log_sh, X_OK) != 0) {
            read_log_sh = "./scripts/read_log.sh";
            if (access(read_log_sh, X_OK) != 0) {
                fprintf(stderr, "Error: read_log.sh not found or not executable.\n");
                return 1;
            }
        }
        
        execl("/bin/bash", "bash", "-p", read_log_sh, target_user, domain, log_type, lines_str, NULL);
        perror("Error: execl failed");
        return 1;
    }

    // Handle run_as subcommand to execute commands as another user context
    if (strcmp(action, "run_as") == 0) {
        if (argc < 4) {
            fprintf(stderr, "Usage: %s run_as <username> <command> [args...]\n", argv[0]);
            return 1;
        }
        const char *target_user = argv[2];
        
        // Security check: Only root and diradmin can run_as other users
        if (uid != 0 && strcmp(pw->pw_name, "diradmin") != 0) {
            fprintf(stderr, "Error: Access denied.\n");
            return 1;
        }
        
        // Validate target_user
        for (const char *p = target_user; *p; p++) {
            if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') || (*p >= '0' && *p <= '9') || *p == '-' || *p == '_')) {
                fprintf(stderr, "Error: Invalid target username.\n");
                return 1;
            }
        }
        
        struct passwd *target_pw = getpwnam(target_user);
        if (!target_pw) {
            fprintf(stderr, "Error: User %s not found.\n", target_user);
            return 1;
        }
        
        // Change group and user to target user
        if (setgid(target_pw->pw_gid) != 0 || setuid(target_pw->pw_uid) != 0) {
            perror("Error: Failed to drop privileges to target user");
            return 1;
        }
        
        // Execute command
        execv(argv[3], &argv[3]);
        perror("Error: execv failed");
        return 1;
    }

    if (strcmp(action, "lock") != 0 && strcmp(action, "unlock") != 0) {
        fprintf(stderr, "Error: Invalid action. Use 'lock', 'unlock', 'update', 'get_domain_config', or 'read_log'.\n");
        return 1;
    }

    
    if (argc != 3) {
        fprintf(stderr, "Usage: %s <lock|unlock> <site_path>\n", argv[0]);
        return 1;
    }
    
    const char *site_path = argv[2];
    
    // 4. Resolve the target path to absolute canonical path
    char real_site_path[PATH_MAX];
    if (realpath(site_path, real_site_path) == NULL) {
        fprintf(stderr, "Error: Invalid or non-existent path: %s\n", site_path);
        return 1;
    }
    
    // 5. Security Boundary check: Ensure path lies inside calling user's home directory
    // root (UID 0) is exempted (e.g. for DirectAdmin admin operations)
    if (uid != 0) {
        size_t home_len = strlen(pw->pw_dir);
        if (strncmp(real_site_path, pw->pw_dir, home_len) != 0 || 
            (real_site_path[home_len] != '\0' && real_site_path[home_len] != '/')) {
            fprintf(stderr, "Error: Access denied. Path must be inside your home directory (%s).\n", pw->pw_dir);
            return 1;
        }
    }
    
    // 6. Safety check: must contain wp-config.php to confirm it is a WordPress installation
    char wp_config[PATH_MAX];
    snprintf(wp_config, sizeof(wp_config), "%s/wp-config.php", real_site_path);
    struct stat st;
    if (stat(wp_config, &st) != 0 || !S_ISREG(st.st_mode)) {
        fprintf(stderr, "Error: Safety block. wp-config.php not found at %s.\n", real_site_path);
        return 1;
    }
    
    // 7. Locate chattr binary on the system
    const char *chattr_path = "/usr/bin/chattr";
    if (access(chattr_path, X_OK) != 0) {
        chattr_path = "/bin/chattr";
        if (access(chattr_path, X_OK) != 0) {
            fprintf(stderr, "Error: chattr binary not found on this system.\n");
            return 1;
        }
    }
    
    const char *mode = (strcmp(action, "lock") == 0) ? "+i" : "-i";
    
    // 8. Execute chattr operations recursively or normally
    char path_buf[PATH_MAX];
    
    // wp-config.php
    execute_chattr(chattr_path, 0, mode, wp_config);
    
    // wp-includes
    snprintf(path_buf, sizeof(path_buf), "%s/wp-includes", real_site_path);
    if (stat(path_buf, &st) == 0 && S_ISDIR(st.st_mode)) {
        execute_chattr(chattr_path, 1, mode, path_buf);
    }
    
    // wp-admin
    snprintf(path_buf, sizeof(path_buf), "%s/wp-admin", real_site_path);
    if (stat(path_buf, &st) == 0 && S_ISDIR(st.st_mode)) {
        execute_chattr(chattr_path, 1, mode, path_buf);
    }
    
    // wp-content/plugins
    snprintf(path_buf, sizeof(path_buf), "%s/wp-content/plugins", real_site_path);
    if (stat(path_buf, &st) == 0 && S_ISDIR(st.st_mode)) {
        execute_chattr(chattr_path, 1, mode, path_buf);
    }
    
    // wp-content/themes
    snprintf(path_buf, sizeof(path_buf), "%s/wp-content/themes", real_site_path);
    if (stat(path_buf, &st) == 0 && S_ISDIR(st.st_mode)) {
        execute_chattr(chattr_path, 1, mode, path_buf);
    }
    
    printf("Success: WordPress files %sed.\n", action);
    return 0;
}
