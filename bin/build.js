const { exec } = require('child_process');
const prompts = require('prompts');
const readline = require('readline');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
let blockName = args[0];

// Define sidebar panels that need to be built
const SIDEBAR_PANELS = [
	'bylines-inspector-sidebar-panel',
	'staff-inspector-sidebar-panel',
];

/**
 * Build a sidebar panel component
 */
function buildSidebarPanel(panelName, chalk) {
	return new Promise((resolve, reject) => {
		const panelPath = `./includes/${panelName}`;
		const srcPath = path.join(panelPath, 'src');
		const buildPath = path.join(panelPath, 'build');

		if (!fs.existsSync(srcPath)) {
			process.stdout.write(
				chalk.yellow(
					`⚠️  Warning: Sidebar panel ${panelName} src not found at ${srcPath}\n`
				)
			);
			resolve();
			return;
		}

		process.stdout.write(
			chalk.blue(`🔧 Building sidebar panel: ${chalk.bold(panelName)}\n`)
		);

		const command = `npx wp-scripts build --source-path=${srcPath} --output-path=${buildPath}`;

		exec(command, (error, stdout, stderr) => {
			if (error) {
				process.stdout.write(
					chalk.red(`❌ Error building ${panelName}: ${stderr}\n`)
				);
				reject(error);
			} else {
				if (stdout) {
					process.stdout.write(stdout);
				}
				process.stdout.write(
					chalk.green(`✅ Sidebar panel ${panelName} built!\n`)
				);
				resolve();
			}
		});
	});
}

/**
 * Build all sidebar panels
 */
async function buildAllSidebarPanels(chalk) {
	process.stdout.write(chalk.blue('🔧 Building sidebar panels...\n'));
	for (const panel of SIDEBAR_PANELS) {
		try {
			await buildSidebarPanel(panel, chalk);
		} catch (error) {
			process.stdout.write(
				chalk.red(`❌ Failed to build sidebar panel: ${panel}\n`)
			);
		}
	}
}

(async () => {
	// Dynamic import for chalk to handle ES module
	const chalk = (await import('chalk')).default;

	if (!blockName) {
		const response = await prompts({
			type: 'text',
			name: 'blockName',
			message: chalk.cyan(
				'Enter the block name, sidebar panel name (bylines-inspector-sidebar-panel, staff-inspector-sidebar-panel), or press enter to build all'
			),
		});
		blockName = response.blockName;
	}

	// Check if this is a sidebar panel build request
	if (SIDEBAR_PANELS.includes(blockName)) {
		await buildSidebarPanel(blockName, chalk);
		return;
	}

	if (
		!blockName ||
		blockName === 'all' ||
		blockName === 'ALL' ||
		blockName === 'library' ||
		blockName === 'LIBRARY'
	) {
		process.stdout.write(chalk.blue('🔨 Building all blocks...\n'));
		exec(
			'npx wp-scripts build --webpack-copy-php',
			(error, stdout, stderr) => {
				if (error) {
					process.stdout.write(chalk.red(`❌ Error: ${stderr}`));
				} else {
					process.stdout.write(stdout);
				}
				process.stdout.write(
					chalk.green('✅ Non-interactive blocks built!\n')
				);

				exec(
					'npx wp-scripts build --experimental-modules --webpack-copy-php',
					(error, stdout, stderr) => {
						if (error) {
							process.stdout.write(
								chalk.red(`❌ Error: ${stderr}`)
							);
						} else {
							process.stdout.write(stdout);
						}
						process.stdout.write(
							chalk.green('✅ Interactive blocks built!\n')
						);

						exec(
							'npx wp-scripts build-blocks-manifest',
							async (error, stdout, stderr) => {
								if (error) {
									process.stdout.write(
										chalk.red(`❌ Error: ${stderr}`)
									);
								} else {
									process.stdout.write(stdout);
								}
								process.stdout.write(
									chalk.green('✅ Blocks manifest built!\n')
								);

								// Build sidebar panels after blocks
								await buildAllSidebarPanels(chalk);
								process.stdout.write(
									chalk.green.bold(
										'\n🎉 All builds complete!\n'
									)
								);
							}
						);
					}
				);
			}
		);
	} else {
		const src = `./src/${blockName}/`;
		const output = `./build/${blockName}/`;

		// Check if src directory exists
		if (!fs.existsSync(src)) {
			process.stdout.write(
				chalk.red(
					`❌ Block does not exist at ${src}. Build process stopped.`
				)
			);
			process.exit(1);
		}

		// Check block.json for interactivity support
		let isInteractive = false;
		const blockJsonPath = path.join(src, 'block.json');
		if (fs.existsSync(blockJsonPath)) {
			try {
				const blockJson = JSON.parse(
					fs.readFileSync(blockJsonPath, 'utf8')
				);
				isInteractive = blockJson.supports?.interactivity || false;
			} catch (error) {
				process.stdout.write(
					chalk.yellow(
						`⚠️  Warning: Could not parse block.json: ${error.message}\n`
					)
				);
			}
		}

		const ellipses = ['.', '..', '...', ''];
		let ellipsesIndex = 0;
		const interval = setInterval(() => {
			readline.cursorTo(process.stdout, 0);
			process.stdout.write(
				chalk.blue(
					`🔨 Building Block Name: ${chalk.bold(blockName)}${ellipses[ellipsesIndex]}`
				)
			);
			ellipsesIndex = (ellipsesIndex + 1) % ellipses.length;
		}, 500);

		// Clear the interval when the build process is done
		let command = `npx wp-scripts build --source-path=${src} --output-path=${output} --webpack-copy-php`;
		if (isInteractive) {
			command += ' --experimental-modules';
			process.stdout.write(
				chalk.magenta(`⚡ Building interactive block: ${blockName}\n`)
			);
		} else {
			process.stdout.write(
				chalk.cyan(`🧱 Building standard block: ${blockName}\n`)
			);
		}
		// Now run the manifest build command
		command +=
			'; npx wp-scripts build-blocks-manifest --input=./src --output=./build/blocks-manifest.php';

		// Execute everything:
		exec(command, (error, stdout, stderr) => {
			process.stdout.write(chalk.gray(`Running command: ${command}\n`));
			clearInterval(interval);
			readline.cursorTo(process.stdout, 0);
			process.stdout.write(' '.repeat(50)); // Clear the line
			readline.cursorTo(process.stdout, 0);
			process.stdout.write(chalk.green('✅ Build complete!\n'));

			if (error) {
				process.stdout.write(chalk.red(`❌ Error: ${stderr}`));
			} else {
				process.stdout.write(stdout);
			}
		});
	}
})();
