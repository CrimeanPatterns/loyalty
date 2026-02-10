const bridge = require('bridge');

async function main() {
    const page = await bridge.connectToPage()
    console.log('connected to page')
    page.on('console', async message => {
        console.log(message)
    })
    console.log('attached console')
    page.on('response', async response => {
        console.log('response received: ' + response.request().resourceType())
        if (response.request().resourceType() === 'xhr') {
            bridge.exitWithResponse(JSON.stringify({body: await response.text(), headers: response.headers()}))
        }
    })

    const iframe = await page.$("iframe[id = 'disneyid-iframe'], iframe[id = 'oneid-iframe']");
    const frame = await iframe.contentFrame();
    const element = await frame.$('button[id = "BtnSubmit"]');
    element.click();
    // page.waitForSelector('button[id = "BtnSubmit"]')
    // page.click('button[id = "BtnSubmit"]')
    console.log('attached page events')
}

main()