
/**
 * This is the main configuration file for OneCode Plugins.
 */
module.exports = {
  /**
   * API数据源配置，通过配置数据源地址，支持用户通过API接口字段与模版绑定
   * API数据源地址支持配置swagger在线地址或noah接口文档地址
   * 如果是noah文档，建议用户从noah接口文档导出JSON文件然后放在项目根目录
   * 例如: 
   * dataSource: [
   *  {
   *    type: 'swagger',
   *    name: 'onecode',
   *    url: 'https://onecode-dev.wanyol.com/user-service/restdocs/v3/api-docs'
   *  },
   *  {
   *    type: 'noah',
   *    name: 'noah',
   *    url: 'api.json' // api.json为noah接口导入到本地的接口文档地址
   *  }
   * ]
   */
  dataSource: [
    // {
    //   type: 'swagger',
    //   url: 'https://onecode-dev.wanyol.com/user-service/restdocs/v3/api-docs',
    //   name: 'onecode'
    // }
  ]
}
